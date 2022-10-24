<?php
require_once './bin/globals.php';

//Login
require_once './bin/api.php';
loginAPI($usernameEN, $passwordEN);

//Funções
function api_get($params) {
	global $usernameEN;
	$ch1 = curl_init( "https://pt.wikipedia.org/w/api.php?" . http_build_query( $params ) );
	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $usernameEN."_cookie.inc" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $usernameEN."_cookie.inc" );
	$data = curl_exec( $ch1 );
	curl_close( $ch1 );
	return $data;
}

//Lista de variantes
$data = [
	"br" => [
		"escrito" 		=> "Categoria:!Artigos escritos em português brasileiro",
		"afinidade"		=> "Categoria:!Artigos que mantêm fortes afinidades com o Brasil",
		"afluir"		=> "MediaWiki:Editnotice-0-Brasil"
	],
	"pt" => [
		"escrito" 		=> "Categoria:!Artigos escritos em português europeu",
		"afinidade"		=> "Categoria:!Artigos com fortes afinidades a Portugal",
		"afluir"		=> "MediaWiki:Editnotice-0-Portugal"
	],
	"mz" => [
		"escrito" 		=> "Categoria:!Artigos escritos em português moçambicano",
		"afinidade"		=> "Categoria:!Artigos com fortes afinidades a Moçambique",
		"afluir"		=> "MediaWiki:Editnotice-0-Moçambique"
	],
	"ao" => [
		"escrito" 		=> "Categoria:!Artigos escritos em português angolano",
		"afinidade"		=> "Categoria:!Artigos com fortes afinidades a Angola",
		"afluir"		=> "MediaWiki:Editnotice-0-Angola"
	]
];

foreach ($data as $iso => $variation) {

	//Mensagem inicial
	echo($variation["afluir"]." em processamento... ");

	//Categoria de artigos escritos da variante
	$escrito_params = [
		"action" 		=> "query",
		"format" 		=> "php",
		"list" 			=> "categorymembers",
		"cmtitle" 		=> $variation["escrito"],
		"cmprop" 		=> "title",
		"cmnamespace" 	=> "1",
		"cmlimit" 		=> "max"
	];
	$escrito_api = unserialize(api_get($escrito_params));
	foreach ($escrito_api["query"]["categorymembers"] as $page) $list_escrito[$iso][] = str_replace("/", "-", substr($page["title"], 11));
	while (isset($escrito_api['continue'])) {
		$escrito_params["cmcontinue"] = $escrito_api['continue']['cmcontinue'];
		$escrito_api = unserialize(api_get($escrito_params));
		foreach ($escrito_api["query"]["categorymembers"] as $page) $list_escrito[$iso][] = str_replace("/", "-", substr($page["title"], 11));
	}

	//Categoria de artigos da variante com forte afinidade
	$afinidade_params = [
		"action" 		=> "query",
		"format" 		=> "php",
		"list" 			=> "categorymembers",
		"cmtitle" 		=> $variation["afinidade"],
		"cmprop" 		=> "title",
		"cmnamespace" 	=> "1",
		"cmlimit" 		=> "max"
	];
	$afinidade_api = unserialize(api_get($afinidade_params));
	foreach ($afinidade_api["query"]["categorymembers"] as $page) $list_afinidade[$iso][] = str_replace("/", "-", substr($page["title"], 11));
	while (isset($afinidade_api['continue'])) {
		$afinidade_params["cmcontinue"] = $afinidade_api['continue']['cmcontinue'];
		$afinidade_api = unserialize(api_get($afinidade_params));
		foreach ($afinidade_api["query"]["categorymembers"] as $page) $list_afinidade[$iso][] = str_replace("/", "-", substr($page["title"], 11));
	}

	//Une as duas categorias
	$list_cats[$iso] = array_unique(array_merge($list_afinidade[$iso], $list_escrito[$iso]));

	//Coleta lista de avisos já existentes via afluentes
	$existentes_params = [
		"action" 		=> "query",
		"format" 		=> "php",
		"list" 			=> "embeddedin",
		"eititle" 		=> $variation["afluir"],
		"einamespace" 	=> "8",
		"eilimit" 		=> "max"
	];
	$existentes_api = unserialize(api_get($existentes_params));
	foreach ($existentes_api["query"]["embeddedin"] as $page) $list_existentes[$iso][] = substr($page["title"], 23);
	while (isset($existentes_api['continue'])) {
		$existentes_params["eicontinue"] = $existentes_api['continue']['eicontinue'];
		$existentes_api = unserialize(api_get($existentes_params));
		foreach ($existentes_api["query"]["categorymembers"] as $page) $list_existentes[$iso][] = substr($page["title"], 23);
	}

	//Verifica se o aviso existente possui correspondencia na categoria
	//Se não, elimina o aviso
	foreach ($list_existentes[$iso] as $existente) {
		if (!in_array($existente, $list_cats[$iso])) {
			deleteAPI(
				"MediaWiki:Editnotice-0-".$existente, 
				"G1 - [[WP:ER#ERg1|Eliminação técnica]] (bot: Eliminando editnotice desnecessário)", 
				$usernameEN
			);
			sleep(10);
			echo("<br>Eliminar MediaWiki:Editnotice-0-".$existente);
		}
	}

	//Verifica se o item da categoria possui aviso correspondente
	//Se não, cria o aviso
	foreach ($list_cats[$iso] as $item_cat) {
		if ($variation["afluir"] == "MediaWiki:Editnotice-0-".$item_cat) continue;
		if (!in_array($item_cat, $list_existentes[$iso])) {
			editAPI(
				"{{:".$variation["afluir"]."}}", 
				NULL, 
				TRUE, 
				"bot: Criando editnotice", 
				"MediaWiki:Editnotice-0-".$item_cat, 
				$usernameEN
			);
			sleep(10);
			echo("<br>Criar MediaWiki:Editnotice-0-".$item_cat);
		}
	}

	//Mensagem final
	echo("OK!<br>");
}