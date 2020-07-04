<?php
echo "<pre>";

$isocode  = "uk";
$template = "Шаблон:Пандемія COVID-19 за країнами та територіями";
$sumario  = "бот: оновлення статистики";
$toadd    = "Території, які потрібно додати";
$toremove = "Території, які потрібно видалити";
$log      = "лог";

function refparser($ref) {
	$de = array(
		"#invoke:WikidataIB|getValue",
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
		"wdib",
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