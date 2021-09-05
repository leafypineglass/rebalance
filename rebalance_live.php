<?php
/*#Rebalance folio live sim version
  By: u/callunquirka aka leafypineglass on github
  License: CC BY
	Version Created: 2020-11-29
	Updated: 2021-09-04
*/

// ##Load
include 'rebalanceConfig.php';
include 'movingAverages.php';

if ($exchange=='binance') {
  require 'vendor/autoload.php';
  include 'binance-data-rest.php';
} else {
  require_once 'KrakenAPIClient.php';
  include 'kraken-data-rest.php';
}

//##Gets price for a single pair
function getPrice ($api, $exchange, $pairSymbol) {
  if ($exchange=='binance') {
    $price = $api->price($pairSymbol);
  } elseif ($exchange=='kraken') {
    $price = $api->QueryPublic('Ticker', array('pair'=>$pairSymbol));
  	$price = $price['result'][$pairSymbol]['c'][0];
  }
  return $price;
}

//##Gets price for multiple pairs
function getTicker ($api, $exchange, $folioArray) {
  $pairSymbols = array_column ($folioArray, 'pairSymbol');

  if ($exchange=='binance') {
    // Binance gives all pairs, and this code gets the desired pairs from the result
    $ticker = $api->prices();
    for($i=0; $i<count($pairSymbols)-1; $i++) {
      $price[$i]=$ticker[$pairSymbols[$i]];
    }
    $ticker = $price;
  } elseif ($exchange=='kraken') {
    // Kraken uses a comma separated string for the symbols
  	array_pop ($pairSymbols);

  	$pairsString = implode(", ",$pairSymbols);
  	$ticker = $api->QueryPublic('Ticker', array('pair'=>$pairsString));
  	$ticker = $ticker['result'];

    $i=0;
    foreach ($ticker as $key => $value) {

      $price[$i] = $value['c'][0];
			settype ($price[$i], 'float');
      $i++;
    }
    $ticker = $price;
  }

  return $ticker;
}

/*##gets OHLC and runs it through the rekey function to get both exchanges giving compatible results
*/
function getOHLC ($api, $exchange, $pairSymbol, $interval, $since) {
  if ($exchange=='binance') {
    $interval = $interval.'m';
    $ohlc = $api->candlesticks($pairSymbol, $interval, $since, null, null, 1000);
    $ohlc = ohlcRekey ($ohlc);
  } elseif ($exchange=='kraken') {
    $ohlc = $api->QueryPublic('OHLC', array('pair' => $pairSymbol,'interval' => $interval, 'since' => $since));
    $ohlc = ohlcRekey($ohlc, $pairSymbol);
  }

  return $ohlc;
}

function installStuffs ($api, $unixTime, $moduleName, $folioArray, $interval){
	$pairCount = count ($folioArray)-1; // last entry is USD

	for ($i=0; $i<$pairCount; $i++){

		$output = installIndicCaller ($api, $unixTime, $folioArray[$i]['pairSymbol'], $moduleName, $interval);

	}
	// Common jsons
	installHistory($api, $moduleName, $folioArray, $output);

}

/* ##Install Hstory
Per pair installs a rebalance-PAIR-history.json which stores list of all sell operations and the averageBuyPrice of the asset.
rebalance-main.json includes a list of all assets, essentially the balance
*/
function installHistory ($api, $moduleName, $folioArray, $installTime) {

	$pairSymbols = array_column ($folioArray, 'pairSymbol');
	array_pop ($pairSymbols);

	$pairsString = implode(", ",$pairSymbols);

  global $exchange;
  $ticker = getTicker ($api, $exchange, $folioArray);

	$pairCount = count($folioArray)-1;

	for ($i=0; $i<$pairCount; $i++) {
		$pairSymbol = $folioArray[$i]['pairSymbol'];
		$coinSymbol = $folioArray[$i]['coinSymbol'];

		/*
		if (isset($balance['result'][$coinSymbol])) {
			$quantity = $balance['result'][$coinSymbol];
			settype($quantity,'float');
		} else {
			$quantity = 0;
		}
		*/

		if ($i<$pairCount) {
			$price = $ticker[$i];
			settype ($price, 'float');
		} else {
			$price = 1;
		}

		$quantity = round($folioArray[$i]['targetPercent']/$price*10, 5, PHP_ROUND_HALF_UP);


		$main[$i] = array ('pairSymbol'=>$pairSymbol, 'coinSymbol'=> $coinSymbol, 'targetPercent'=>$folioArray[$i]['targetPercent'], 'quantity'=>$quantity, 'averageBuyPrice'=>$price, 'startQuantity'=>$quantity);

		$historyTotals = array ('profit'=>0, 'quoteProfit'=>0, 'totalDiff'=>0, 'count'=>0, 'positive'=>0, 'posPercent'=>0);

		$history = array ('ledger'=>null, 'totals'=>$historyTotals);

		$filename = $moduleName. '-'. $folioArray[$i]['pairSymbol'].'-';
		$filepath = '' . $filename . 'history.json';
		file_put_contents($filepath, json_encode($history));
	}


	$quantity = $folioArray[$pairCount]['targetPercent']*10;
	$main[] = array ('pairSymbol'=>'ZUSD', 'coinSymbol'=>'ZUSD','targetPercent'=>$folioArray[$pairCount]['targetPercent'],  'quantity'=>$quantity, 'averageBuyPrice'=>1, 'startQuantity'=>$quantity);
	$filename = $moduleName. '-';
	$filepath = '' . $filename . 'main.json';
	file_put_contents($filepath, json_encode($main));


	$quickExt = array ('lastPosInt'=>$installTime, 'pairIndex'=>0, 'updateTime'=>$installTime, 'indicCurrent'=>'current');
	$filepath = '' . $filename . 'QuickExt.json';
	file_put_contents($filepath, json_encode($quickExt));


	$ledgerTotals = array ('profit'=>0, 'quoteProfit'=>0, 'totalDiff'=>0, 'count'=>0, 'positive'=>0, 'posPercent'=>0);

	$ledger = array ('ledger'=>null, 'totals'=>$ledgerTotals);

	$filepath = '' . $moduleName . '-ledger.json';
	file_put_contents($filepath, json_encode($ledger));
}

// Gets OHLC of 1 pair and passes it to indic calculator
function installIndicCaller ($api, $unixTime, $pairSymbol, $moduleName, $interval) {
	$days = 30;
	$since = $unixTime-(60*60*24*$days);

  global $exchange;
  $ohlc = getOHLC($api, $exchange, $pairSymbol, $interval, $since);

	if (is_array($ohlc)) {

		$currentFile=$moduleName.'-'.$pairSymbol.'-';
		$output = installIndicR($currentFile, $ohlc);

		if ($output!=null) {
			print_r ($pairSymbol.' '.$output. ' installed<br>');
		}
	} else {
		exit ('not array');
	}
	return $output;
}

function installIndicR ($filename, $ohlc) {

  $priceEMA = null;

  $open = null;
  $high = null;
  $low =  null;
  $close = null;
  $volume = null;

  $ohlcCount = count($ohlc);
  for ($i = 0; $i<$ohlcCount; $i++) {

    $open[$i] = $ohlc[$i]['open'];
    $high[$i] = $ohlc[$i]['high'];
    $low[$i] = $ohlc[$i]['low'];
    $close[$i] = $ohlc[$i]['close'];
    $volume[$i] = $ohlc[$i]['volume'];

      // PRICE EMA
      $priceEMAPeriod = 24;
      if($i>=$priceEMAPeriod){
        $highTemp = array_slice ($high, $i-$priceEMAPeriod, $priceEMAPeriod);
        $lowTemp = array_slice ($low, $i-$priceEMAPeriod, $priceEMAPeriod);
        $priceEMA[$i] = frama($close[$i], $highTemp, $lowTemp, $priceEMA[$i-1], $priceEMAPeriod);
      } elseif ($i>0 && $i<$priceEMAPeriod) {
        $priceEMA[$i] = ema($close[$i], $priceEMA[$i-1], $priceEMAPeriod);
      } elseif ($i==0) {
        $priceEMA[$i] = $close[$i];
      }

      $priceEMAPeriod = 54;
      if($i>=$priceEMAPeriod){
        $highTemp = array_slice ($high, $i-$priceEMAPeriod, $priceEMAPeriod);
        $lowTemp = array_slice ($low, $i-$priceEMAPeriod, $priceEMAPeriod);
        $priceEMAMed[$i] = frama($close[$i], $highTemp, $lowTemp, $priceEMAMed[$i-1], $priceEMAPeriod);
      } elseif ($i>0 && $i<$priceEMAPeriod) {
        $priceEMAMed[$i] = ema($close[$i], $priceEMAMed[$i-1], $priceEMAPeriod);
      } elseif ($i==0) {
        $priceEMAMed[$i] = $close[$i];
      }

      $priceEMAPeriod = 74;
      if($i>=$priceEMAPeriod){
        $highTemp = array_slice ($high, $i-$priceEMAPeriod, $priceEMAPeriod);
        $lowTemp = array_slice ($low, $i-$priceEMAPeriod, $priceEMAPeriod);
        $priceEMASlow[$i] = frama($close[$i], $highTemp, $lowTemp, $priceEMASlow[$i-1], $priceEMAPeriod);
      } elseif ($i>0 && $i<$priceEMAPeriod) {
        $priceEMASlow[$i] = ema($close[$i], $priceEMASlow[$i-1], $priceEMAPeriod);
      } elseif ($i==0) {
        $priceEMASlow[$i] = $close[$i];
      }

      $emaSet[$i] = array (
        'time'=>$ohlc[$i]['time'],
        'priceEMA'=>$priceEMA[$i],
        'priceEMAMed'=>$priceEMAMed[$i],
        'priceEMASlow'=>$priceEMASlow[$i],
      );
    }

    if(!file_exists('indic/')){
       mkdir('indic/', 0755);
   }

		$filepath = 'indic/' . $filename . 'ohlcOld.json';
	  file_put_contents($filepath, json_encode($ohlc));

    $filepath = 'indic/' . $filename . 'emaSet.json';
    file_put_contents($filepath, json_encode($emaSet));

		return ($ohlc[$ohlcCount-1]['time']);
}

function indicUPR ($filename, $emaSet, $ohlc) {
  if (count ($ohlc)>2){
    $ohlc = array_slice ($ohlc, -2);// grab end of array
  }else {
    print ("ohlcUncut");
  }

  $filepath = 'indic/' . $filename . 'ohlcOld.json';
  $ohlcOld = file_get_contents($filepath);
  $ohlcOld = json_decode($ohlcOld, true);

  end($ohlcOld);
  $ohlcCount = key($ohlcOld);

  end($emaSet);
  $lastKey = key($emaSet);

  $ohlcOld[]= $ohlc[1];

  $i = $lastKey+1;
  $ohlcI = $ohlcCount+1;

  $rowTime = $ohlc[1]['time'];

  $emaSet[$i]['time'] = $rowTime;

  // Price EMAs

  $oldHigh = array_column($ohlcOld, 'high');
  $oldLow = array_column($ohlcOld, 'low');

  $priceEMAPeriod = 24;

  $highTemp = array_slice ($oldHigh, $ohlcI-$priceEMAPeriod, $priceEMAPeriod);
  $lowTemp = array_slice ($oldLow, $ohlcI-$priceEMAPeriod, $priceEMAPeriod);
  $emaSet[$i]['priceEMA'] = frama($ohlcOld[$ohlcI]['close'], $highTemp, $lowTemp, $emaSet[$i-1]['priceEMA'], $priceEMAPeriod);


  $priceEMAPeriod = 54;

  $highTemp = array_slice ($oldHigh, $ohlcI-$priceEMAPeriod, $priceEMAPeriod);
  $lowTemp = array_slice ($oldLow, $ohlcI-$priceEMAPeriod, $priceEMAPeriod);
  $emaSet[$i]['priceEMAMed'] = frama($ohlcOld[$ohlcI]['close'], $highTemp, $lowTemp, $emaSet[$i-1]['priceEMAMed'], $priceEMAPeriod);

  $priceEMAPeriod = 74;

  $highTemp = array_slice ($oldHigh, $ohlcI-$priceEMAPeriod, $priceEMAPeriod);
  $lowTemp = array_slice ($oldLow, $ohlcI-$priceEMAPeriod, $priceEMAPeriod);
  $emaSet[$i]['priceEMASlow'] = frama($ohlcOld[$ohlcI]['close'], $highTemp, $lowTemp, $emaSet[$i-1]['priceEMASlow'], $priceEMAPeriod);

  array_shift ($ohlcOld);
  $filepath = 'indic/' . $filename . 'ohlcOld.json';
  file_put_contents($filepath, json_encode($ohlcOld));

  array_shift ($emaSet);
  $filepath = 'indic/' . $filename . 'emaSet.json';
  file_put_contents($filepath, json_encode($emaSet));
}

// gets called by updateMain
function updateFunctions ($api, $folioArray, $targetPair, $interval, $date, $since, $unixTime, $intervalSec, $moduleName) {
	$ohlc = null;
	$rowTime = null;

	$pairName = $folioArray[$targetPair]['pairSymbol'];

	$currentFile =$moduleName.'-'.$pairName.'-';

	$filepath = 'indic/' . $currentFile . 'emaSet.json';
	$emaSet = file_get_contents($filepath);
	$emaSet = json_decode($emaSet, true);

	end($emaSet);
	$lastKey = key($emaSet);

	// update


	$compareTime = $unixTime-$intervalSec*3;

	if ($emaSet[$lastKey]['time']<=$compareTime){ //reinstall if too many missing
			$days = 30;
			$since = $unixTime-(60*60*24*$days);

      global $exchange;
      $ohlc = getOHLC($api, $exchange, $pairName, $interval, $since);

			if (is_array($ohlc)){
				$current = installIndicR($currentFile, $ohlc);
				$rowTime = $ohlc[count($ohlc)-1]['time'];
				if ($current!=null) {
					print ($folioArray[$targetPair]['pairSymbol'].' '.$current.' ok <br>');
				}
			} else {
				exit ('Error API JSON empty');
			}

	}else {

    global $exchange;
    $ohlc = getOHLC($api, $exchange, $pairName, $interval, $since);

		if (is_array($ohlc)) {
			$rowTime = $ohlc[count($ohlc)-1]['time'];

			if ($rowTime > $emaSet[$lastKey]['time']){
				indicUPR($currentFile, $emaSet, $ohlc);
				print ($folioArray[$targetPair]['pairSymbol'].' ok <br>');
			}
			if ($rowTime == $emaSet[$lastKey]['time']){
				print ($folioArray[$targetPair]['pairSymbol'].' ok <br>');
			}
		} else {
			$rowTime = null;
		}
	}

	return $rowTime;
}
/* ##  updatemain
Checks each pair for updatedness and calls updateFunctions() which in turn calls indicUPR().
*/

function updateMain ($api, $unixTime, $folioArray, $moduleName, $interval, $quickExt) {
	$pairCount = count($folioArray)-2; // last entry is USD

	$intervalSec = $interval*60;
	$intervalSec2 = $intervalSec*2;

	$date = date('Y-m-d', $unixTime);

	$since = $unixTime-5400;

	$compareTime = $unixTime-$intervalSec;

	if ($quickExt['updateTime']<$compareTime) { //check total update status
		$pairsUpdated = $quickExt['pairIndex'];
		$updateTime = 0;

		if ($pairsUpdated+2>=$pairCount) {
			$updateTarget = $pairCount;
		} else {
			$updateTarget = $pairsUpdated+2;
		}

		for ($i=$pairsUpdated; $i<=$updateTarget; $i++) { //for every pair, check time of latest indic row
			if ($folioArray[$i]['coinSymbol']=='ZUSD') {
				print ('mew');
			}
			$currentUpdate = 0;

			$pairName = $folioArray[$i]['pairSymbol'];
			$currentFile = $moduleName.'-'.$pairName.'-';

			$filepath = 'indic/' . $currentFile . 'emaSet.json';
			$emaSet = file_get_contents($filepath);
			$emaSet = json_decode($emaSet, true);

			$compareTime = $unixTime-$intervalSec2;
			if ($emaSet[count($emaSet)-1]['time']>=$compareTime){
				$pairsUpdated +=1;
				$currentUpdate = 1;
				$quickExt['pairIndex'] = $pairsUpdated;
				$updateTime = $emaSet[count($emaSet)-1]['time'];
				print_r ($pairName.'-update not needed<br>');
			}
			if ($currentUpdate==0){ // If current pair isn't update, run update functions
				$updateTime = updateFunctions ($api, $folioArray, $i, $interval, $date, $since, $unixTime, $intervalSec, $moduleName);
				if ($updateTime!=null) {
					$pairsUpdated +=1;
					$quickExt['pairIndex'] = $pairsUpdated;

					$filepath = ''.$moduleName.'-'.'QuickExt.json'; // update pair index
					file_put_contents($filepath, json_encode($quickExt));
					print ('updateRan ');
				} else {
					exit ('error');
				}
			}
			unset ($emaSet);
			if (is_int($i/3)){
				usleep(330000); // sleep for 1/3 of a second
			}
		}
	}
	if ($quickExt['pairIndex']==$pairCount+1){
		$quickExt['updateTime'] = $updateTime;
		$quickExt['pairIndex'] = 0;
		print ('<br>'.$quickExt['updateTime'].'..'.$pairsUpdated.'..</br>');

		$filepath = ''.$moduleName.'-'.'QuickExt.json'; // update pair index
		file_put_contents($filepath, json_encode($quickExt));
	}

}

/*
## Buy Potential
Asset has already been checked that it's below threshold. This function chooses whether it buys all or buys a bit.
$folioEntry example:
```
$DOTUSD = array (
   'pairSymbol'=>'DOTUSD',
	 'coinSymbol'=>'DOT',
	 'targetPercent' => 5,
);
```
*/
function buyPotential ($api, $moduleName, $currentPercent, $folioEntry, $totalValue, $price) {
	$filename = $moduleName. '-'. $folioEntry['pairSymbol'].'-';

	$filepath = 'indic/' . $filename . 'ohlcOld.json';
	$ohlcOld = file_get_contents($filepath);
	$ohlcOld = json_decode($ohlcOld, true);

	$filepath = 'indic/' . $filename . 'emaSet.json';
	$emaSet = file_get_contents($filepath);
	$emaSet = json_decode($emaSet, true);

	$filepath = '' . $moduleName . '-main.json';
	$main = file_get_contents($filepath);
	$main = json_decode($main, true);


	end($ohlcOld);
	$lastKey = key($ohlcOld);

	$pairSymbol = $folioEntry['pairSymbol'];
	$haystack = array_column($main, 'pairSymbol');
	$mainEntry = array_search($pairSymbol, $haystack, TRUE);

	$mainLast = count($main)-1;

  global $exchange;
  $price = getPrice ($api, $exchange, $pairSymbol);

	if (!isset($price)) {
		print ('no price');
	}


	// Buy if lower than threshold
	if ($currentPercent>1 && $ohlcOld[$lastKey]['close']>$emaSet[$lastKey]['priceEMAMed'] && isset($price)) {
		$dev = $folioEntry['targetPercent']-$currentPercent;
		$dev = round($dev*$totalValue/100, 2, PHP_ROUND_HALF_DOWN);

		// check there is enough USD
		if ($main[$mainLast]['quantity']>$dev) {
				$toBuy = round($dev/$price, 5, PHP_ROUND_HALF_DOWN);
		} else {
				$toBuy = $main[$mainLast]['quantity']*0.92;
				$toBuy = round($toBuy/$price, 5, PHP_ROUND_HALF_DOWN);
		}

		$toBuy = $toBuy-$toBuy*0.004; // fee and drift
		$newAveragePrice = $main[$mainEntry]['quantity']*$main[$mainEntry]['averageBuyPrice'] + $toBuy * $price;
		$newAveragePrice = $newAveragePrice/($main[$mainEntry]['quantity'] + $toBuy);
		$newAveragePrice = round($newAveragePrice,5, PHP_ROUND_HALF_UP);

		$main[$mainEntry]['quantity'] +=$toBuy;
		$main[$mainEntry]['averageBuyPrice'] = $newAveragePrice;

		$main[$mainLast]['quantity'] -= $dev;

		print ($folioEntry['pairSymbol'].' bought');
	}

	// Re-buy if preveiously sold all. Only dumpable ones apply.

	if ($currentPercent<=1 && $ohlcOld[$lastKey]['close']<$ohlcOld[$lastKey-1]['close'] && $emaSet[$lastKey]['priceEMA']>$emaSet[$lastKey]['priceEMASlow'] && isset($price)) {
		$dev = $folioEntry['targetPercent'];
		$dev = $dev*$totalValue/100;

		// check there is enough USD
		if ($main[$mainLast]['quantity']>$dev) {
				$toBuy = round($dev/$price, 5, PHP_ROUND_HALF_DOWN);
		} else {
				$toBuy = $main[$mainLast]['quantity']*0.92;
				$toBuy = round($toBuy/$price, 5, PHP_ROUND_HALF_DOWN);
		}

		$toBuy  = $toBuy- $toBuy*0.004; // fee and drift

		$main[$mainEntry]['quantity'] = $toBuy;
		$main[$mainEntry]['averageBuyPrice'] = $price;

		$main[$mainLast]['quantity'] -= $dev;

		print ($folioEntry['pairSymbol'].' bought from zero');
	}



	$filepath = '' . $moduleName . '-main.json';
	file_put_contents($filepath, json_encode($main));
}

/*
## Sell Potential
Asset has already been checked that it's above threshold. This function chooses whether it sells all or sells a bit.
$folioEntry example:
```
$DOTUSD = array (
   'pairSymbol'=>'DOTUSD',
	 'coinSymbol'=>'DOT',
	 'targetPercent' => 5,
);
```
*/
function sellPotential ($api, $moduleName, $currentPercent, $folioEntry, $totalValue, $price, $unixTime, $threshold) {
	$filename = $moduleName. '-';

	$filepath = 'indic/' . $filename . $folioEntry['pairSymbol']. '-ohlcOld.json';
	$ohlcOld = file_get_contents($filepath);
	$ohlcOld = json_decode($ohlcOld, true);

	$filepath = 'indic/' . $filename . $folioEntry['pairSymbol']. '-emaSet.json';
	$emaSet = file_get_contents($filepath);
	$emaSet = json_decode($emaSet, true);

	$filepath = '' . $moduleName . '-main.json';
	$main = file_get_contents($filepath);
	$main = json_decode($main, true);

	$filepath = '' . $moduleName. '-'. $folioEntry['pairSymbol'].'-' . 'history.json';
	$history = file_get_contents($filepath);
	$history = json_decode($history, true);

	//$historyTotals = array ('profit'=>0, 'quoteProfit'=>0, 'totalDiff'=>0, 'count'=>0, 'positive'=>0, 'posPercent'=>0);
	//$history = array ('ledger'=>null, 'totals'=>$historyTotals);

	if (!isset($history)) {
		print ('zero history');
	}

	end($ohlcOld);
	$lastKey = key($ohlcOld);

	$pairSymbol = $folioEntry['pairSymbol'];
	$haystack = array_column($main, 'pairSymbol');
	$mainEntry = array_search($pairSymbol, $haystack, TRUE);

	$mainLast = count($main)-1;

	// Sell if higher than threshold
	// || $emaSet[$lastKey]['priceEMA']>$emaSet[$lastKey]['priceEMASlow']

	if ($folioEntry['dumpable']==0 || $ohlcOld[$lastKey]['close']>=$emaSet[$lastKey]['priceEMASlow']) {
		$devPercent = $currentPercent - $folioEntry['targetPercent'];
		$dev = round($devPercent*$totalValue/100, 2, PHP_ROUND_HALF_DOWN);

		$toSell = round($dev/$price, 5, PHP_ROUND_HALF_DOWN);

		$soldValue = $toSell*$price;
		$fee = 0.004*$soldValue;
		$soldValue = $soldValue- $fee; // fee and drift

		//$historyTotals = array ('profit'=>0, 'quoteProfit'=>0, 'totalDiff'=>0, 'count'=>0, 'positive'=>0, 'posPercent'=>0);
		//$history = array ('ledger'=>null, 'totals'=>$historyTotals);

		// History Stuff
		$originalValue = $toSell*$main[$mainEntry]['averageBuyPrice'];
		$profitPercent = round(($soldValue-$originalValue)/$originalValue*100, 2, PHP_ROUND_HALF_UP);

		$profitValue = $soldValue-$originalValue;

		$history['ledger'][] = array (
			'time'=>$unixTime,
			'volume'=>$toSell,
			'value'=>$soldValue,
			'fee'=>$fee,
			'profitPercent'=>$profitPercent,
			'profitValue'=>$profitValue,
		);

		$history['totals']['profit'] += $profitPercent;
		$history['totals']['quoteProfit'] += $profitValue;
		$history['totals']['totalDiff'] += $fee;
		$history['totals']['count'] += 1;

		if ($profitValue>0) {
			$history['totals']['positive'] += 1;
			$history['totals']['posPercent'] = round($history['totals']['positive']/$history['totals']['count']*100, 2, PHP_ROUND_HALF_UP);
		}

		// Main Stuff

		$newAveragePrice = round(($main[$mainEntry]['quantity']*$main[$mainEntry]['averageBuyPrice'] + $toSell * $price)/($main[$mainEntry]['quantity'] + $toSell),5, PHP_ROUND_HALF_UP);

		$main[$mainEntry]['quantity'] -=$toSell;
		$main[$mainEntry]['averageBuyPrice'] = $newAveragePrice;

		$main[$mainLast]['quantity'] += $soldValue;

		print ($folioEntry['pairSymbol'].' sold');
	}

	// Sell all aka dump. Only dumpable ones apply.

	if ($folioEntry['dumpable']==1  && $ohlcOld[$lastKey]['close']<$emaSet[$lastKey]['priceEMASlow'] && $devPercent>$threshold) {

		$soldValue = $main[$mainEntry]['quantity']*$price;
		$soldValue -= 0.004*$soldValue; // fee and drift

		$fee = 0.004*$soldValue;

		// History Stuff
		$originalValue = $main[$mainEntry]['quantity']*$main[$mainEntry]['averageBuyPrice'];
		$profitPercent = round(($soldValue-$originalValue)/$originalValue*100, 2, PHP_ROUND_HALF_UP);

		$profitValue = $soldValue-$originalValue;

		$history['ledger'][] = array (
			'time'=>$unixTime,
			'volume'=>$main[$mainEntry]['quantity'],
			'value'=>$soldValue,
			'fee'=>$fee,
			'profitPercent'=>$profitPercent,
			'profitValue'=>$profitValue,
		);

		$history['totals']['profit'] += $profitPercent;
		$history['totals']['quoteProfit'] += $profitValue;
		$history['totals']['totalDiff'] += $fee;
		$history['totals']['count'] += 1;

		if ($profitValue>0) {
			$history['totals']['positive'] += 1;
			$history['totals']['posPercent'] = round($history['totals']['positive']/$history['totals']['count']*100, 2, PHP_ROUND_HALF_UP);
		}

		$main[$mainEntry]['quantity'] = 0;
		$main[$mainEntry]['averageBuyPrice'] = 0;

		$main[$mainLast]['quantity'] += $soldValue;

		print ($folioEntry['pairSymbol'].' sold (dump)');
	}



	$filepath = '' . $moduleName . '-main.json';
	file_put_contents($filepath, json_encode($main));

	$filepath = '' . $moduleName. '-'. $folioEntry['pairSymbol'].'-' . 'history.json';
	file_put_contents($filepath, json_encode($history));
}

/*
## Balance Check
Main checker function
Path: Check account balance, decide if there is an opportunity to sell

Everytime there is a buy, it recalculates the average price of the buy and when it sells it records profit
Store average price at point of buy
So there are two files: Average buy price for each item (main.json), and a file for the record of each sell written as one transaction

*/
function balanceCheck ($api, $moduleName, $folioArray, $threshold, $unixTime) {


  // get trade balance would go here


	// get current % of each coin and ema or other indicators

	$filepath = '' . $moduleName . '-main.json';
	$main = file_get_contents($filepath);
	$main = json_decode($main, true);

	$pairSymbols = array_column($folioArray, 'pairSymbol');
	array_pop($pairSymbols);

	$pairCount = count($folioArray)-1; //last is USD
	for ($i=0; $i<$pairCount; $i++) {
		if ($folioArray[$i]['coinSymbol']=='ZUSD') {
			print ('USD');
		}
		$currentPair = $folioArray[$i]['pairSymbol'];

		$filename = $moduleName.'-'.$folioArray[$i]['pairSymbol']. '-';

		$filepath = 'indic/' . $filename . 'ohlcOld.json';
	  $ohlcOld = file_get_contents($filepath);
	  $ohlcOld = json_decode($ohlcOld, true);

		end($ohlcOld);
		$lastKey = key($ohlcOld);

		$price[$i] = $ohlcOld[$lastKey]['close'];
		$priceT[$i] = $ticker[$currentPair]['c'][0];

		$currentValue[$i] = $main[$i]['quantity']*$price[$i];
		$holdValue[$i] = $main[$i]['startQuantity']*$price[$i];

	}
	$totalValue = array_sum($currentValue);
	$totalValue += $main[$pairCount]['quantity']; // add USD

  // Hold value is the value if one only holds from the beginning
	$holdValue = array_sum($holdValue);
	$holdValue += $main[$pairCount]['startQuantity']; // add USD

	$operation = null;

	// based on deviation from planned % of folio, do stuff for each thing
	for ($i=0; $i<$pairCount; $i++) {  // count($folioArray)-1 since last is USD
		$filename = $moduleName.'-'.$folioArray[$i]['pairSymbol']. '-';
		if ($folioArray[$i]['coinSymbol']=='ZUSD') {
			print ('squeak');
		}

		if ($folioArray[$i]['coinSymbol']!='ZUSD') {
			$currentPercent[$i] = round ($currentValue[$i]/$totalValue*100, 3, PHP_ROUND_HALF_UP);
			$deviation = $currentPercent[$i]-$main[$i]['targetPercent'];

      //Potential checks if it is worth buying or selling, depending on status of indicators
			if ($deviation>$threshold) {
				sellPotential ($api, $moduleName, $currentPercent[$i], $folioArray[$i], $totalValue, $priceT[$i], $unixTime, $threshold);
			}
			if ($deviation<-$threshold) {
				buyPotential ($api, $moduleName, $currentPercent[$i], $folioArray[$i], $totalValue, $priceT[$i]);
			}
			$operation[] = $folioArray[$i]['pairSymbol'].' balance checked';
		}
	}



	$filepath = ''.$moduleName.'-'.'QuickExt.json';
	$quickExt = file_get_contents($filepath);
	$quickExt = json_decode($quickExt, true);

	$quickExt['tempTotalValue'] = $totalValue;
	$quickExt['holdValue'] = $holdValue;

	for ($i=0; $i<=$pairCount; $i++) { // includes USD
		$quickExt['volChange'][$folioArray[$i]['coinSymbol']] = round($main[$i]['quantity']/$main[$i]['startQuantity']*100, 2, PHP_ROUND_HALF_UP);
	}


	file_put_contents($filepath, json_encode($quickExt));

	print_r ($operation);
}



$unixTime = time();

if ($exchange=='binance') {
  $api = new Binance\API($key, $secret);
} elseif ($exchange=='kraken'){
  $api = new KrakenAPI($key, $secret);
}


if ($install==1) {
  installStuffs ($api, $unixTime, $moduleName, $folioArray, $interval);
} else {

  $filepath = ''.$moduleName.'-'.'QuickExt.json';
  $quickExt = file_get_contents($filepath);
  $quickExt = json_decode($quickExt, true);

  updateMain ($api, $unixTime, $folioArray, $moduleName, $interval, $quickExt);


  $intervalSec = $interval*60;
  $minuteOfInterval = ($unixTime-$quickExt['updateTime']-$intervalSec)/60;

  if ($minuteOfInterval >= 5 && $minuteOfInterval < 13 ) {
  	balanceCheck ($api, $moduleName, $folioArray, $threshold, $unixTime);
  }
}
/*

*/
?>
