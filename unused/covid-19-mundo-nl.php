<?php
echo "<pre>";

$isocode  = "nl";
$template = "Sjabloon:Zijbalk tabel uitbraak SARS-CoV-2";
$sumario  = "bot: update statistieken";
$toadd    = "Nog toe te voegen gebieden";
$toremove = "Te verwijderen gebieden";
$log      = "log";
$ignorerate = TRUE;
$ignorepop = TRUE;
$ignoretitle = TRUE;

function refparser($ref) {
	$de = array(
		"|script-",
		"|url-status=live",
		" January 202",
		" February 202",
		" March 202",
		" April 202",
		" May 202",
		" June 202",
		" July 202",
		" August 202",
		" September 202",
		" October 202",
		" November 202",
		" December 202"
	);
	$para = array(
		"|",
		"",
		"-01-202",
		"-02-202",
		"-03-202",
		"-04-202",
		"-05-202",
		"-06-202",
		"-07-202",
		"-08-202",
		"-09-202",
		"-10-202",
		"-11-202",
		"-12-202"
	); 
	$refparsed = str_replace($de, $para, preg_replace('/date=([0-9]{4})-([0-9]{2})-([0-9]{2})/', 'date=$3-$2-$1', trim($ref)));
	return $refparsed;
}

require_once './bin/globals.php';
require_once './bin/covid-19-mundo.php';