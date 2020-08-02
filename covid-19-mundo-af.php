<?php
echo "<pre>";

$isocode  = "af";
$template = "Sjabloon:Koronaviruspandemie van 2019-2020";
$sumario  = "bot: Opdatering van statistieke";
$toadd    = "Gebiede om by te voeg";
$toremove = "Gebiede om te verwyder";
$log      = "log";
$ignorecurados = TRUE;

function refparser($ref) {
	$de = array(
		"|url-status=live"
	);
	$para = array(
		""
	); 
	$refparsed = str_replace($de, $para, preg_replace('/date=([0-9]{4})-([0-9]{2})-([0-9]{2})/', 'date=$3-$2-$1', trim($ref)));
	return $refparsed;
}

include './bin/globals.php';
include './bin/covid-19-mundo.php';