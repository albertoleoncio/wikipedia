<?php
include './bin/globals.php';

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login($usernameBQ, $passwordBQ))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

function blockrequest ($pagina) {
	
	//Verifica se página existe
	global $wiki;
	$page = $wiki->getPage($pagina);
	if (!$page->exists()) die('Page not found');

	//Recupera códig-fonte da página, dividida por seções
	$sections = $page->getAllSections(true);

	//Conta quantidade de seções
	$count = count($sections);

	//Loop para análise de cada seção
	for ($i=0; $i < $count; $i++) { 

		//Reseta varíavel de regex
		unset($regex);

		//Proteção contra duplicação de seções
		preg_match_all('/\n==/', $sections[$i], $dupl_sect);
		if (isset($dupl_sect['0']['0'])) die;

		//Verifica se pedido ainda está aberto
		preg_match_all("/<!--\n?{{Respondido/", $sections[$i], $regex);

		//Caso não esteja aberto, interrompe loop e segue para a próxima seção
		if (!isset($regex['0']['0'])) continue;

		//Divide seção por linhas
		$lines = explode("\n", $sections[$i]);

		//Recupera nome de usuário
		$user = trim($lines['0'], "= ");

		//Coleta informações do usuário
		$info = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=blocks&bkusers=".urlencode($user)), true)['query']['blocks'];

		//Caso não esteja bloqueado, interrompe loop e segue para a próxima seção
		if (!isset($info[0])) {
			continue;
		} else {
			$blockinfo = $info[0];
		}

		//Caso bloqueio seja menor que 24 horas, interrompe loop e segue para a próxima seção
		if ($blockinfo['expiry'] != "infinity" AND (strtotime($blockinfo['expiry']) - strtotime($blockinfo['timestamp']) < 90000)) continue;

		//Define tempo de bloqueio
		if ($blockinfo['expiry'] == "infinity") {
			$tempo = "tempo indeterminado";
		} else {
			$interval = date_diff(date_create($blockinfo['timestamp']), date_create($blockinfo['expiry']));
			$tempo = "";
			if ($interval->format('%y') != 0) $tempo = $tempo.$interval->format('%y')." ano(s), ";
			if ($interval->format('%m') != 0) $tempo = $tempo.$interval->format('%m')." mese(s), ";
			if ($interval->format('%d') != 0) $tempo = $tempo.$interval->format('%d')." dia(s), ";
			if ($interval->format('%h') != 0) $tempo = $tempo.$interval->format('%h')." hora(s), ";
			if ($interval->format('%i') != 0) $tempo = $tempo.$interval->format('%i')." minuto(s), ";
			if ($interval->format('%s') != 0) $tempo = $tempo.$interval->format('%s')." segundo(s), ";
			$tempo = trim($tempo, ", ");
		}

		//Substitui seção inicial
		$sections[$i] = preg_replace('/<!--\n?{{Respondido2[^>]*>/', '{{Respondido2|feito|texto=', $sections[$i]);

		//Substitui seção final
		$sections[$i] = preg_replace(
			'/<!--\n?:{{subst:(Bloqueio )?[Ff]eito[^>]*>/', 
			":{{subst:Bloqueio feito|por=".$blockinfo['by']."|".$tempo."}}. [[User:BloqBot|BloqBot]] ~~~~~}}", 
			$sections[$i]
		);

		//Grava seção
		if ($page->setText($sections[$i], $i, true, "bot: Fechando pedido cumprido")) {
			echo "Gravando ".$user."<br>";
		} else {
			$error = $page->getError();
			echo "<hr>Error: ".print_r($error, true)."\n";
		}

		//Reseta varíaveis
		unset($sections);
		unset($page);
	}
}

//Executa função em páginas
blockrequest('Wikipédia:Pedidos/Notificações de vandalismo');
blockrequest('Wikipédia:Pedidos/Revisão de nomes de usuário');