<?php
echo "<pre>";

$isocode  = "az";
$template = "Şablon:2019–20 koronavirus pandemiyası verilənləri";
$sumario  = "bot: Statistikanı yeniləyir";
$toadd    = "Əlavə ediləcək ərazilər";
$toremove = "İstisna ediləcək ərazilər";
$log      = "log";
$ignorerate = TRUE;
$ignorepop = TRUE;

function refparser($ref) {
	$de = array(
		"|script-",
		"| name-list-format = vanc ",
		"|url-status=live",
		" March 2020",
		" April 2020",
		" May 2020",
		" June 2020",
		" July 2020"
	);
	$para = array(
		"|",
		"",
		"",
		"-03-2020",
		"-04-2020",
		"-05-2020",
		"-06-2020",
		"-07-2020"
	); 
	$refparsed = str_replace($de, $para, preg_replace('/date=([0-9]{4})-([0-9]{2})-([0-9]{2})/', 'date=$3-$2-$1', trim($ref)));
	return $refparsed;
}

include './bin/globals.php';
include './bin/covid-19-mundo.php';