<?php
include './bin/globals.php';

//Login
include './bin/api.php';
loginAPI($username, $password);

//Funções
function api_get($params) {
	global $username;
	$ch1 = curl_init( "https://pt.wikipedia.org/w/api.php?" . http_build_query( $params ) );
	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $username."_cookie.inc" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $username."_cookie.inc" );
	$data = curl_exec( $ch1 );
	curl_close( $ch1 );
	return $data;
}

//Listar subpáginas da central de confiabilidade
$list_subpages_params = [
	"action" 		=> "query",
	"format" 		=> "php",
	"list" 			=> "allpages",
	"apprefix" 		=> "Fontes confiáveis/Central de confiabilidade/",
	"apnamespace" 	=> "4",
	"apfilterredir" => "nonredirects",
	"aplimit" 		=> "500"
];
$list_subpages_api = unserialize(api_get($list_subpages_params))["query"]["allpages"];
foreach ($list_subpages_api as $subpage) $list_subpages[] = $subpage["title"];

//Listar páginas da categoria de rejeitadas
$list_rejected = array('');
$list_rejected_params = [
	"action"  => "query",
	"format"  => "php",
	"list"    => "categorymembers",
	"cmtitle" => "Categoria:!Propostas de fontes não confiáveis rejeitadas",
	"cmprop"  => "title",
	"cmsort"  => "timestamp",
	"cmlimit" => "500"
];
$list_rejected_api = unserialize(api_get($list_rejected_params))["query"]["categorymembers"];
foreach ($list_rejected_api as $rejected) $list_rejected[] = $rejected["title"];

//Cria array de propostas aprovadas
$list_approved = array_diff($list_subpages, $list_rejected);

//Processa cada proposta aprovada
foreach ($list_approved as $approved) {

	//Procura número da última seção de nível principal
	$approved_sections_params = [
		"action" 	=> "parse",
		"format" 	=> "php",
		"page" 		=> $approved,
		"prop" 		=> "sections"
	];
	$approved_sections = unserialize(api_get($approved_sections_params))["parse"]["sections"];
	$section_last = "0";
	foreach ($approved_sections as $section) {
		if ($section["level"] == "2") $section_last = $section["index"];
	}

	//Recupera código-fonte da última seção
	$section_last_wikitext_params = [
		"action" 	=> "parse",
		"format" 	=> "php",
		"page" 		=> $approved,
		"prop" 		=> "wikitext",
		"section" 	=> $section_last
	];
	$section_last_wikitext = unserialize(api_get($section_last_wikitext_params))["parse"]["wikitext"]["*"];

	//Captura parâmetros via regex
	preg_match_all('/\| *?nome *?= *?\K[^\|]*/', $section_last_wikitext, $nome);
	preg_match_all('/\| *?área *?= *?\K[^\|]*/', $section_last_wikitext, $area);
	preg_match_all('/\| *?domínio1 *?= *?\K[^\|]*/', $section_last_wikitext, $dominio1);
	preg_match_all('/\| *?domínio2 *?= *?\K[^\|]*/', $section_last_wikitext, $dominio2);
	preg_match_all('/\| *?domínio3 *?= *?\K[^\|]*/', $section_last_wikitext, $dominio3);
	preg_match_all('/\| *?domínio4 *?= *?\K[^\|]*/', $section_last_wikitext, $dominio4);
	preg_match_all('/\| *?domínio5 *?= *?\K[^\|]*/', $section_last_wikitext, $dominio5);
	preg_match_all('/\| *?timestamp *?= *?\K[^\|]*/', $section_last_wikitext, $timestamp);

	//Insere dados na array
	$valid = isset($nome["0"]["0"]) ? trim($nome["0"]["0"]) : false;
	if(!empty($valid)) $general_list[trim($nome["0"]["0"])] = [
		"area" => @trim($area["0"]["0"]),
		"dominio1" => @trim($dominio1["0"]["0"]),
		"dominio2" => @trim($dominio2["0"]["0"]),
		"dominio3" => @trim($dominio3["0"]["0"]),
		"dominio4" => @trim($dominio4["0"]["0"]),
		"dominio5" => @trim($dominio5["0"]["0"]),
		"timestamp" => @trim($timestamp["0"]["0"])
	];
}

//Monta lista de fontes
$wikicode = '{| class="wikitable sortable" style="font-size: 87%;"
|+ Lista de fontes não confiáveis
|-
! Nome !! Área !! Dia de inclusão !! Domínio(s) associado(s)';
foreach ($general_list as $key => $value) {
	$wikicode .= "\n|-\n| [[WP:Fontes confiáveis/Central de confiabilidade/".$key."|".$key."]] || ".$value["area"]." || ".date("d/m/Y", ((int)$value["timestamp"]))." || <nowiki>".$value["dominio1"];
	if ($value["dominio2"] != FALSE) $wikicode .= ", ".$value["dominio2"];
	if ($value["dominio3"] != FALSE) $wikicode .= ", ".$value["dominio3"];
	if ($value["dominio4"] != FALSE) $wikicode .= ", ".$value["dominio4"];
	if ($value["dominio5"] != FALSE) $wikicode .= ", ".$value["dominio5"];
	$wikicode .= "</nowiki>";
}
$wikicode .= "\n|}";

//Salva página
editAPI($wikicode, NULL, FALSE, "bot: Atualizando lista", "Wikipédia:Fontes não confiáveis/Lista", $username);
