<?php
echo "<pre>";

$isocode  = "cs";
$template = "Wikipedista:AlbeROBOT/Pískoviště";
$sumario  = "bot: [[Special:diff/18913955|Test]]";
$toadd    = "Území přidat";
$toremove = "Území odstranit";
$log      = "log";
$ignoreref = TRUE;
$ignorerate = TRUE;
$ignorepop = TRUE;

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