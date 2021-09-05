<?php

//Set install to 1 to install, change to 0 after installing
$moduleName = 'rebalance';
$install = 1;

//##API stuff
//DISABLE WITHDRAWAL FOR API PERMISSIONS
global $exchange;
$exchange = 'binance'; // 'kraken' or 'binance'
//$key = 'keyHere';
//$secret = 'secretHere';


// if coin is 10% of folio and it goes up 30%, then the folio portion goes up by 0.3%
// if coin 5% of folio, it needs 60% movement to trigger threshold since the portion of the folio is up by 0.3% then
$threshold = 0.3;
$interval = 30; // in minutes

//##Pairs and their percentages for rebalance. Requires USD as the last item.
// dumpable = 1 for if you wanna let it dump. Does not seem to make a big difference.
$BTCUSDC = array (
   'pairSymbol'=>'BTCUSDC',
   'coinSymbol'=>'BTC',
	 'targetPercent' => 33,
	 'dumpable' => 1,
);
$ETHUSDC = array (
   'pairSymbol'=>'ETHUSDC',
	 'coinSymbol'=>'ETH',
	 'targetPercent' => 20,
	 'dumpable' => 1,
);
$LTCUSDC = array (
   'pairSymbol'=>'LTCUSDC',
	 'coinSymbol'=>'LTC',
	 'targetPercent' => 10,
	 'dumpable' => 1,
);
$ADAUSDC = array (
   'pairSymbol'=>'ADAUSDC',
	 'coinSymbol'=>'ADA',
	 'targetPercent' => 15,
	 'dumpable' => 1,
);
$LINKUSDC = array (
   'pairSymbol'=>'LINKUSDC',
	 'coinSymbol'=>'LINK',
	 'targetPercent' => 12,
	 'dumpable' => 0,
);
$usdArray = array (
   'pairSymbol'=>'USDC',
	 'coinSymbol'=>'USDC',
	 'targetPercent' => 10,
	 'dumpable' => 0,
);

// USD must be the last item
$folioArray = array ($BTCUSDC, $ETHUSDC, $LTCUSDC, $ADAUSDC, $LINKUSDC, $usdArray);

?>
