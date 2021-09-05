<?php

function ohlcRekey ($ohlc, $pair){
   $ohlcRekey = null;
   if ($ohlc['error']==null) {

      if (isset($ohlc['result'][$pair])){
         $ohlc = $ohlc['result'][$pair];
      }

      array_pop ($ohlc);
      $i=0;
      foreach ($ohlc as $value){
         $ohlcRekey[$i]['time'] = $value[0];
         $ohlcRekey[$i]['open'] = $value[1];
         $ohlcRekey[$i]['high'] = $value[2];
         $ohlcRekey[$i]['low'] = $value[3];
         $ohlcRekey[$i]['close'] = $value[4];
         $ohlcRekey[$i]['vwap'] = $value[5];
         $ohlcRekey[$i]['volume'] = $value[6];

         settype($ohlcRekey[$i]['time'], 'float');
         settype($ohlcRekey[$i]['open'], 'float');
         settype($ohlcRekey[$i]['high'], 'float');
         settype($ohlcRekey[$i]['low'], 'float');
         settype($ohlcRekey[$i]['close'], 'float');
         settype($ohlcRekey[$i]['vwap'], 'float');
         settype($ohlcRekey[$i]['volume'], 'float');
         $i++;
      }
   } else {
     exit ('OHLC Recieve Error');
   }
   return $ohlcRekey;
}

function tradesRekey ($trades, $pair){
   $tradesRekey = null;
   if ($trades['error']==null) {

      if (isset($trades['result'][$pair])){
         $trades = $trades['result'][$pair];
      }

      $i=0;
      foreach ($trades as $value){

         $tradesRekey['result'][$i]['price'] = $value[0];
         $tradesRekey['result'][$i]['volume'] = $value[1];
         $tradesRekey['result'][$i]['time'] = $value[2];
         $tradesRekey['result'][$i]['buySell'] = $value[3];
         $tradesRekey['result'][$i]['marketLimit'] = $value[4];
         $i++;
      }
   }else {
      exit ('Error Recieved');
   }
   return $tradesRekey;
}


?>
