<?php
include './bin/globals.php';

//Login
include './bin/api.php';
loginAPI($usernameBQ, $passwordBQ);

//Coleta categoria de usuários notificados
$list = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmtitle=Category%3A!Usu%C3%A1rios%20com%20nomes%20impr%C3%B3prios%20notificados&cmprop=title%7Ctimestamp&cmsort=timestamp&cmlimit=500"), true)["query"]["categorymembers"];

//Loop para cada usuário da categoria
foreach ($list as $item) {

	//Coleta nome da página de discussão do usuário
	$usertalk = $item["title"];

	//Coleta informações do usuário
	$info = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=blocks&bkusers=".urlencode(substr($usertalk, 23))), true)['query']['blocks'];

	//Verifica se usuário está bloqueado
	if (!isset($info[0])) {

		//Verifica se prazo de 5 dias foi decorrido. Caso sim, interrompe loop e segue para o próximo usuário
		if ((date("U", strtotime($item["timestamp"])) + 432000) > time()) continue;

		//Define página de discussão do usuário e recupera codigo-fonte da página
		$page = $usertalk;
		$html = getAPI($page);

		//Gravar código
		editAPI($html, NULL, TRUE, "", $page, $usernameBQ);

	} else {

		//Define variável
		$blockinfo = $info[0];

		//Caso bloqueio seja menor que 24 horas, interrompe loop e segue para o próximo usuário
		if ($blockinfo['expiry'] != "infinity") continue;

		//Define página de discussão do usuário e recupera codigo-fonte da página
		$page = $usertalk;
		$html = getAPI($page);

		//Remove categorização
		$html = preg_replace('/{{#ifeq:[^\|]*\|{{PAGENAME}}.*solucionados\]\]}}/', '', $html);

		//Gravar código
		editAPI($html, NULL, TRUE, "bot: Removendo categoria de nome impróprio", $page, $usernameBQ);

		//Limpa variável para próximo loop
		unset($page);
	}
}

echo("<hr>");

//Coleta categoria de usuários notificados
$list2 = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmtitle=Categoria%3A!Usu%C3%A1rios%20com%20nomes%20impr%C3%B3prios%20pass%C3%ADveis%20de%20bloqueio&cmprop=title&cmsort=timestamp&cmlimit=500"), true)["query"]["categorymembers"];

//Loop para cada usuário da categoria
foreach ($list2 as $item2) {

	//Coleta nome da página de discussão do usuário
	$usertalk = $item2["title"];

	//Coleta informações do usuário
	$info2 = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=blocks&bkusers=".urlencode(substr($usertalk, 23))), true)['query']['blocks'];

	//Verifica se usuário está bloqueado
	if (isset($info2[0])) {

		//Define página de discussão do usuário e recupera codigo-fonte da página
		$page = $usertalk;
		$html = getAPI($page);

		//Remove categorização
		$html = preg_replace('/{{#ifeq:[^\|]*\|{{PAGENAME}}.*solucionados\]\]}}/', '', $html);

		//Gravar código e passa para o próximo usuário
		editAPI($html, NULL, TRUE, "bot: Removendo categoria de nome impróprio", $page, $usernameBQ);
		continue;

	}

	//Coleta afluentes da página de usuário
	$afluentes = end(json_decode(file_get_contents('https://pt.wikipedia.org/w/api.php?action=query&format=json&prop=linkshere&titles='.urlencode($item2["title"])), true)["query"]["pages"]);

	//Verifica se já há pedido de revisão ou renomeação para a conta
	if (isset($afluentes["linkshere"])) {
		if (!is_null(array_search("6286011", array_column($afluentes["linkshere"], 'pageid')))) continue;
		if (!is_null(array_search("2077627", array_column($afluentes["linkshere"], 'pageid')))) continue;
	}

	//Define página de pedidos e recupera codigo-fonte da página
	$page = "Wikipédia:Pedidos/Revisão de nomes de usuário";
	$html = getAPI($page);

	//Insere pedido no código
	$html = $html."\n{{subst:Nome de usuário impróprio/BloqBot|".substr($item2["title"], 23)."}}\n";

	//Gravar código
	editAPI($html, NULL, FALSE, "bot: Inserindo pedido de usuário notificado há 5 dias", $page, $usernameBQ);
} 