<pre><?php
include './bin/globals.php';
date_default_timezone_set('UTC');

//Login
include './bin/api.php';
loginAPI($username, $password);

/*
	get_subcategories.php

	MediaWiki API Demos
	Demo of `Categorymembers` module : Get ten subcategories of a category

	MIT License
*/

$endPoint = "https://pt.wikipedia.org/w/api.php";
$params_cat = [
	"action" => "query",
	"list" => "categorymembers",
	"cmtitle" => "Categoria:!Ficheiros para eliminação semirrápida/dia ".date("j", strtotime("-1 day")),
	"format" => "json"
];

$ch_cat = curl_init( $endPoint . "?" . http_build_query( $params_cat ) );
curl_setopt( $ch_cat, CURLOPT_RETURNTRANSFER, true );
$output_cat = curl_exec( $ch_cat );
curl_close( $ch_cat );
$result_cat = json_decode( $output_cat, true );

//Loop de análise de cada arquivo encontrado
foreach( $result_cat["query"]["categorymembers"] as $file ) {

	$params_file = [
		"action" => "query",
		"prop" => "imageinfo",
		"titles" => $file["title"],
		"format" => "json"
	];
	
	$ch_file = curl_init( $endPoint . "?" . http_build_query( $params_file ) );
	curl_setopt( $ch_file, CURLOPT_RETURNTRANSFER, true );
	$output_file = curl_exec( $ch_file );
	curl_close( $ch_file );
	$result_file = json_decode( $output_file, true );

	//Recupera data de envio do arquivo
	$timestamp = strtotime(pos($result_file['query']['pages'])['imageinfo']['0']['timestamp']);

	//Condicional para verificar se arquivo foi enviado antes de 28/05/2011 (dif:25470547)
	if ($timestamp < 1306540800) {

		//Recupera conteúdo do arquivo
		$page = $file["title"];
		$wikiCode = getAPI($page);

		//Insere: modificado = sim
		$wikiCode = str_replace("nformação","nformação\n| modificado = sim", $wikiCode);

		//Gravar código
		editAPI($wikiCode, NULL, true, "bot: Inserindo parâmetro \"modificado\" para evitar eliminação ([[Predefinição Discussão:Informação#Pergunta_técnica_II|detalhes]])", $page, $username);
	}
}