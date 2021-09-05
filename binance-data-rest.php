<?php

//Binance data restructure

function ohlcRekey ($ohlc){
   $ohlcRekey = null;
   $i = 0;
   array_pop ($ohlc);

   foreach ($ohlc as $value){
      $ohlcRekey[$i] = $value;
      $time = substr($value['openTime'], 0, -3);
      $closeTime = substr($value['closeTime'], 0, -3);
      settype($time, 'float');
      $ohlcRekey[$i]['time'] = $time;
      $ohlcRekey[$i]['openTime'] = $time;
      $ohlcRekey[$i]['closeTime'] = $closeTime;
      $i++;
   }
   return $ohlcRekey;
}


function orderBook (array $orders, $count) {

   $bids = null;
   $asks = null;

   for ($i=0; $i<$count; $i++) {
      $key = key($orders['asks']);
      $asks[$i] = array ($key, $orders['asks'][$key]);
      next($orders['asks']);

      $key = key($orders['bids']);
      $bids[$i] = array ($key, $orders['bids'][$key]);
      next($orders['bids']);
   }

   $orders = array ('bids'=>$bids, 'asks'=> $asks);
   return $orders;
}

function timeFormat ($time) {
   $time = substr($time['serverTime'], 0, -3);
   return($time);
}


?>
