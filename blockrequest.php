<?php
include './bin/globals.php';

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

////////////////////////////////////////////////////////////////////////////////////////
//
//	Notificações de vandalismo
//
////////////////////////////////////////////////////////////////////////////////////////

//Verifica se página existe
$page1 = $wiki->getPage('Wikipédia:Pedidos/Notificações de vandalismo');
if (!$page1->exists()) die('Page not found');

//Recupera códig-fonte da página, dividida por seções
$sections = $page1->getAllSections(true);

//Conta quantidade de seções
$count = count($sections);

//Loop para análise de cada seção
for ($i=0; $i < $count; $i++) { 

	//Reseta varíavel de regex
	unset($regex);

	//Verifica se pedido ainda está aberto
	preg_match_all('/<!--\n{{Respondido/', $sections[$i], $regex);

	//Caso não esteja aberto, interrompe loop e segue para a próxima seção
	if (!isset($regex['0']['0'])) continue;

	//Divide seção por linhas
	$lines = explode("\n", $sections[$i]);

	//Recupera nome de usuário
	$user = trim($lines['0'], "= ");
	
	//Coleta informações do usuário
	$usercontribs = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=users&usprop=blockinfo&uclimit=1&ususers=".urlencode($user)), true)['query']['users'][0];

	//Caso não esteja bloqueado, interrompe loop e segue para a próxima seção
	if (!isset($usercontribs['blockid'])) continue;

	//Caso bloqueio seja menor que 24 horas, interrompe loop e segue para a próxima seção
	if ($usercontribs['blockexpiry'] != "infinite" AND (strtotime($usercontribs['blockexpiry']) - strtotime($usercontribs['blockedtimestamp']) < 90000)) continue;

	//Define tempo de bloqueio
	if ($usercontribs['blockexpiry'] == "infinite") {
		$tempo = "tempo indeterminado";
	} else {
		$interval = date_diff(date_create($usercontribs['blockedtimestamp']), date_create($usercontribs['blockexpiry']));
		if ($interval->format('%h') % 24 == 0) {
			$tempo = $interval->format('%a dia(s)');
		} else {
			$tempo = $interval->format('%h hora(s)');
		}
	}

	//Substitui seção inicial
	$sections[$i] = preg_replace('/<!--\n{{Respondido2[^>]*>/', '{{Respondido2|feito|texto=', $sections[$i]);

	//Substitui seção final
	$sections[$i] = preg_replace(
		'/<!--\n:{{subst:Bloqueio feito[^>]*>/', 
		":{{subst:Bloqueio feito|por=".$usercontribs['blockedby']."|".$tempo."}}. ~~~~}}", 
		$sections[$i]
	);

	//Grava seção
	if ($page1->setText($sections[$i], $i, true, "bot: Fechando pedido cumprido")) {
		echo "Gravando ".$user."<br>";
	} else {
		$error = $page1->getError();
		echo "<hr>Error: ".print_r($error, true)."\n";
	}
}

//Reseta varíavel de seções
unset($sections);

////////////////////////////////////////////////////////////////////////////////////////
//
//	Wikipédia:Pedidos/Revisão de nomes de usuário
//
////////////////////////////////////////////////////////////////////////////////////////

//Verifica se página existe
$page2 = $wiki->getPage('Wikipédia:Pedidos/Revisão de nomes de usuário');
if (!$page2->exists()) die('Page not found');

//Recupera códig-fonte da página, dividida por seções
$sections = $page2->getAllSections(true);

//Conta quantidade de seções
$count = count($sections);

//Loop para análise de cada seção
for ($i=0; $i < $count; $i++) { 

	//Reseta varíavel de regex
	unset($regex);

	//Verifica se pedido ainda está aberto
	preg_match_all('/<!--{{Respondido2/', $sections[$i], $regex);

	//Caso não esteja aberto, interrompe loop e segue para a próxima seção
	if (!isset($regex['0']['0'])) continue;

	//Divide seção por linhas
	$lines = explode("\n", $sections[$i]);

	//Recupera nome de usuário
	$user = trim($lines['0'], "= ");
	
	//Coleta informações do usuário
	$usercontribs = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=users&usprop=blockinfo&uclimit=1&ususers=".urlencode($user)), true)['query']['users'][0];

	//Caso não esteja bloqueado, interrompe loop e segue para a próxima seção
	if (!isset($usercontribs['blockid'])) continue;

	//Caso bloqueio seja menor que 24 horas, interrompe loop e segue para a próxima seção
	if ($usercontribs['blockexpiry'] != "infinite" AND (strtotime($usercontribs['blockexpiry']) - strtotime($usercontribs['blockedtimestamp']) < 90000)) continue;

	//Define se tempo de bloqueio é infinito. Caso contrário, interrompe loop e segue para a próxima seção
	if ($usercontribs['blockexpiry'] != "infinite") continue;

	//Substitui seção inicial
	$sections[$i] = preg_replace('/<!--{{Respondido2[^>]*>/', '{{Respondido2|feito|texto=', $sections[$i]);

	//Substitui seção final
	$sections[$i] = preg_replace(
		'/<!--\:{{subst\:Feito[^>]*>/', 
		":{{subst:Bloqueio feito|por=".$usercontribs['blockedby']."|tempo indeterminado}}. [[User:AlbeROBOT|AlbeROBOT]] ~~~~~}}", 
		$sections[$i]
	);

	//Grava seção
	if ($page2->setText($sections[$i], $i, true, "bot: Fechando pedido cumprido")) {
		echo "Gravando ".$user."<br>";
	} else {
		$error = $page2->getError();
		echo "<hr>Error: ".print_r($error, true)."\n";
	}
}