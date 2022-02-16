<?php
include './bin/globals.php';

//Login
include './bin/api.php';
loginAPI($usernameBQ, $passwordBQ);

//Função api_get
function api_get($params) {
	global $usernameBQ;
	$ch1 = curl_init( "https://pt.wikipedia.org/w/api.php?" . http_build_query( $params ) );
	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch1, CURLOPT_COOKIEJAR, $usernameBQ."_cookie.inc" );
	curl_setopt( $ch1, CURLOPT_COOKIEFILE, $usernameBQ."_cookie.inc" );
	$data = curl_exec( $ch1 );
	curl_close( $ch1 );
	return $data;
}

//Coleta categoria de usuários notificados
$params_cat = [
	"action"  => "query",
	"format"  => "php",
	"list"    => "categorymembers",
	"cmtitle" => "Categoria:!Usuários_com_nomes_impróprios_notificados",
	"cmprop"  => "title|timestamp",
	"cmsort"  => "timestamp",
	"cmlimit" => "500"
];
$list = unserialize(api_get($params_cat))["query"]["categorymembers"];

//Loop para cada usuário da categoria
foreach ($list as $item) {

	//Coleta nome da página de discussão do usuário
	$usertalk = $item["title"];

	//Coleta informações do usuário
	$params_blocks = [
		"action"  => "query",
		"format"  => "php",
		"list"    => "blocks",
		"bkusers" => preg_replace('/.*?:/', '', $usertalk)
	];
	$info = unserialize(api_get($params_blocks))['query']['blocks'];

	//Verifica se usuário está bloqueado
	if (!isset($info[0])) {

		//Verifica se prazo de 5 dias foi decorrido. Caso sim, interrompe loop e segue para o próximo usuário
		if ((date("U", strtotime($item["timestamp"])) + 432000) > time()) continue;

		//Purga página de discussão do usuário para recarregar categorias
		$params_purge = [
			"action"          => "purge",
			"format"          => "php",
			"forcelinkupdate" => "1",
			"titles"          => $usertalk
		];
		api_get($params_purge);

	} else {

		//Define variável
		$blockinfo = $info[0];

		//Caso bloqueio seja menor que 24 horas, interrompe loop e segue para o próximo usuário
		if ($blockinfo['expiry'] != "infinity") continue;

		//Define página de discussão do usuário e recupera codigo-fonte da página
		$page = $usertalk;
		$html = getAPI($page);

		//Remove categorização
		$html = preg_replace('/{{#ifeq:[^\|]*\|{{PAGENAME}}\|{{#ifexpr:.*\]\]}}}}/', '', $html);

		//Gravar código
		editAPI($html, NULL, TRUE, "bot: Removendo categoria de nome impróprio", $page, $usernameBQ);

		//Limpa variável para próximo loop
		unset($page);
	}
}

echo("<hr>");

//Coleta categoria de usuários notificados
$params_cat2 = [
	"action"  => "query",
	"format"  => "php",
	"list"    => "categorymembers",
	"cmtitle" => "Categoria:!Usuários_com_nomes_impróprios_passíveis_de_bloqueio",
	"cmprop"  => "title",
	"cmsort"  => "timestamp",
	"cmlimit" => "500"
];
$list2 = unserialize(api_get($params_cat2))["query"]["categorymembers"];

//Define página de pedidos
$page = "Wikipédia:Pedidos/Revisão de nomes de usuário";

//Loop para cada usuário da categoria
foreach ($list2 as $item2) {

	//Coleta nome da página de discussão do usuário
	$usertalk = $item2["title"];

	//Coleta informações do usuário
	$params_blocks = [
		"action"  => "query",
		"format"  => "php",
		"list"    => "blocks",
		"bkusers" => preg_replace('/.*?:/', '', $usertalk)
	];
	$info2 = unserialize(api_get($params_blocks))['query']['blocks'];

	//Verifica se usuário está bloqueado
	if (isset($info2[0])) {

		//Define página de discussão do usuário e recupera codigo-fonte da página
		$page = $usertalk;
		$html = getAPI($page);

		//Remove categorização
		$html = preg_replace('/{{#ifeq:[^\|]*\|{{PAGENAME}}\|{{#ifexpr:.*\]\]}}}}/', '', $html);

		//Gravar código e passa para o próximo usuário
		editAPI($html, NULL, TRUE, "bot: Removendo categoria de nome impróprio", $page, $usernameBQ);
		continue;

	}

	//Coleta afluentes da página de usuário
	$params_blocks = [
		"action"  => "query",
		"format"  => "php",
		"prop"    => "linkshere",
		"titles"  => $item2["title"]
	];
	$afluentes = end(unserialize(api_get($params_blocks))["query"]["pages"]);

	//Verifica se já há pedido de revisão ou renomeação para a conta
	if (isset($afluentes["linkshere"])) {
		if (array_search("6286011", array_column($afluentes["linkshere"], 'pageid')) !== FALSE) continue;
		if (array_search("2077627", array_column($afluentes["linkshere"], 'pageid')) !== FALSE) continue;
	}

	//Prepara código de pedido
	$html = "\n\n{{subst:Nome de usuário impróprio/BloqBot|".preg_replace('/.*?:/', '', $item2["title"])."}}";

	//Gravar código
	editAPI($html, "append", FALSE, "bot: Inserindo pedido de usuário notificado há 5 dias", $page, $usernameBQ);
} 

echo("OK!");