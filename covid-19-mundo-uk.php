<?php
echo "<pre>";

$isocode  = "uk";
$template = "Шаблон:Пандемія COVID-19 за країнами та територіями";
$sumario  = "бот: оновлення статистики";
$toadd    = "Території, які потрібно додати";
$toremove = "Території, які потрібно видалити";
$logpage  = "Користувач:AlbeROBOT/log";

include './bin/globals.php';
include './bin/covid-19-mundo.php';