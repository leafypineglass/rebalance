<?php
/* Indicators only
	Version Created: 2020-11-29
	Updated: 2020-11-29
*/

function ema ($value, $prevValue, $period) {
	$value = $value*(2/($period+1))+$prevValue*(1-2/($period+1));
	$value = round($value, 7, PHP_ROUND_HALF_UP);
	return $value;
}

function zema ($value, $lagValue, $prevZEMA, $period) {
	$data = $value+($value-$lagValue);
	$zema = ema ($data, $prevZEMA, $period);
	$zema = round($zema, 7, PHP_ROUND_HALF_UP);
	return $zema;
}

function frama ($value, $high, $low, $prevFrama, $period){

	$T = $period/2;

	$max1 = array_slice ($high, 0, $T-1);
	$max2 = array_slice ($high, $T);
	$min1 = array_slice ($low, 0, $T-1);
	$min2 = array_slice ($low, $T);

	$N1 = (max($max1)-min($min1))/$T;
	$N2 = (max($max2)-min($min2))/$T;
	$N3 = (max($high)-min($low))/$period;

	$D = round((log($N1+$N2)-log($N3))/log(2),5, PHP_ROUND_HALF_UP);
	$alpha = -4.6*($D-1);
	$alpha = exp($alpha);

	if ($alpha <0.01){
		$alpha = 0.01;
	}
	if ($alpha >1){
		$alpha = 1;
	}
	if ( is_nan($alpha) ) {
		$alpha = 0.01;
	}
	$newFrama = $prevFrama + $alpha*($value-$prevFrama);

	$newFrama = round($newFrama, 7, PHP_ROUND_HALF_UP);
	return $newFrama;
}

?>
