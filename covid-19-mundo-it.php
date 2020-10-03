<?php
echo "<pre>";

//Limite de horário - https://it.wikipedia.org/wiki/Wikipedia:Bot#Policy_d'uso_ed_etica_del_manovratore
if ((strftime("%H") >= 22) OR (rtrim(strftime("%H"), "0") < 6)) die("Horário não permitido: ".strftime("%H"));

$isocode  = "it";
$template = "Template:Dati della pandemia di COVID-19 del 2019-2020";
$sumario  = "bot: Aggiornamento delle statistiche";
$toadd    = "Territori da aggiungere";
$toremove = "Territori da rimuovere";
$log      = "log";
$ignorerate = TRUE;
$ignorepop = TRUE;

function refparser($ref) {
	$de = array(
		"|url-status=live",
		" March 2020",
		" April 2020",
		" May 2020",
		" June 2020",
		" July 2020",
		" August 2020",
		" September 2020",
		" October 2020",
		" November 2020",
		" December 2020"
	);
	$para = array(
		"",
		"-03-2020",
		"-04-2020",
		"-05-2020",
		"-06-2020",
		"-07-2020",
		"-08-2020",
		"-09-2020",
		"-10-2020",
		"-11-2020",
		"-12-2020"
	); 
	$refparsed = str_replace($de, $para, preg_replace('/date=([0-9]{4})-([0-9]{2})-([0-9]{2})/', 'date=$3-$2-$1', trim($ref)));
	return $refparsed;
}

include './bin/globals.php';
include './bin/covid-19-mundo.php';