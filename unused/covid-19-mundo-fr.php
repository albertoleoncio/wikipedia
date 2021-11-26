<?php
echo "<pre>";

$isocode  = "fr";
$template = "Modèle:Données de la pandémie de Covid-19";
$sumario  = "bot: Mise à jour des statistiques";
$toadd    = "Territoires à ajouter";
$toremove = "Territoires à supprimer";
$log      = "log";

function refparser($ref) {
	$de = array(
		"><ref",
		"|url-status=live",
		"{{cite news|",
		"script-title=",
		" March 2020"
	);
	$para = array(
		">{{,}}<ref",
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