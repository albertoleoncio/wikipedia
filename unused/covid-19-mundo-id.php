<?php
echo "<pre>";

$isocode  = "id";
$template = "Templat:Data pandemi COVID-19";
$sumario  = "bot: Memperbarui statistik";
$toadd    = "Wilayah yang akan ditambahkan";
$toremove = "Wilayah yang akan dihapus";
$log      = "log";
$ignorerate = TRUE;
$ignorepop = TRUE;

function refparser($ref) {
	$de = array(
		"|script-",
		"| name-list-format = vanc ",
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