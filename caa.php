<?php
include './bin/globals.php';

//Define fuso horário como UTC
date_default_timezone_set('UTC');

//Define data atual
$today = strtotime('today');

//Recupera lista de artigos
$atual = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=parse&format=json&text=%7B%7B%23tag%3ADynamicPageList%7C%0Acategory%20%3D%20!Candidaturas%20a%20artigo%20pendentes%0Acount%20%20%20%20%3D%2030%0Aordermethod%20%3D%20categoryadd%0Aorder%20%3D%20ascending%0Aaddfirstcategorydate%20%3D%20true%0Asuppresserrors%20%3D%20true%0A%7D%7D&contentmodel=wikitext"), true)['parse']['text']['*'];

//Isola lista de dias
preg_match_all('/<li>([^:]*): /', $atual, $output_array);

//Arrays de substituição de meses
$de = array(
	'de janeiro de',
	'de fevereiro de',
	'de março de',
	'de abril de',
	'de maio de',
	'de junho de',
	'de julho de',
	'de agosto de',
	'de setembro de',
	'de outubro de',
	'de novembro de',
	'de dezembro de'
);
$para = array(
	'January',
	'February',
	'March',
	'April',
	'May',
	'June',
	'July',
	'August',
	'September',
	'October',
	'November',
	'December',
);

//Define contadores de candidatos vencidos
$today = 0;
$yesterday = 0;
$old = 0;

//Define timestamps de hoje e ontem
$timestamp_today = strtotime(date('j F Y',time())." -30 days"); 
$timestamp_yesterday = $timestamp_today - 86400;

//Loop para contabilizar cada candidato a artigo
foreach ($output_array['1'] as $line) {
	$timestamp_article = strtotime(str_replace($de, $para, $line));
	if ($timestamp_article > $timestamp_today) {
		//Fazer nada
	} elseif ($timestamp_article == $timestamp_today) {
		$today++;
	} elseif ($timestamp_article == $timestamp_yesterday) {
		$yesterday++;
	} elseif ($timestamp_article < $timestamp_yesterday) {
		$old++;
	}
}

//Monta código
$wikiCode = "<!--
-->{{#ifeq:{{{1}}}|hoje|".$today."}}<!--
-->{{#ifeq:{{{1}}}|ontem|".$yesterday."}}<!--
-->{{#ifeq:{{{1}}}|antigo|".$old."}}";

//Login
include './bin/api.php';
loginAPI($username, $password);

//Define endereço da predefinição
$page = 'Predefinição:Painel dos administradores/CAA';

//Grava código
editAPI($wikiCode, 0, true, "bot: Atualizando contador de canditatos a artigo", $page, $username);