<?php
echo "<pre>";

$isocode  = "fr";
$template = "Modèle:Données de la pandémie de maladie à coronavirus de 2019-2020";
$sumario  = "bot: Mise à jour des statistiques";
$toadd    = "Territoires à ajouter";
$toremove = "Territoires à supprimer";
$log      = "log";

function refparser($ref) {
	$de = array(
		"</ref><ref>",
		"|url-status=live",
		"{{cite news|",
		"script-title=",
		" March 2020"
	);
	$para = array(
		"</ref>{{,}}<ref>",
		"",
		"{{cite web|",
		"title=",
		"-03-2020"
	); 
	$refparsed = str_replace($de, $para, trim($ref));
	return $refparsed;
}

include './bin/globals.php';
include './bin/covid-19-mundo.php';