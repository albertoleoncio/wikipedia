<?php
echo "<pre>";

$isocode  = "el";
$template = "Πρότυπο:Δεδομένα πανδημίας κορονοϊού 2019–20";
$sumario  = "bot: Ενημέρωση στατιστικών";
$toadd    = "Περιοχή - προσθήκη";
$toremove = "Περιοχή - για να αφαιρέσετε";
$log      = "log";

function refparser($ref) {
	$de = array(
		"#invoke:WikidataIB|getValue",
		"|script-",
		"|lang=",
		"| name-list-format = vanc ",
		"|url-status=live"
	);
	$para = array(
		"wdib",
		"|",
		"|language=",
		"",
		""
	); 
	$refparsed = str_replace($de, $para, trim($ref));
	return $refparsed;
}

include './bin/globals.php';
include './bin/covid-19-mundo.php';