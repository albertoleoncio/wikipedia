<?php
include './bin/globals.php';
date_default_timezone_set('UTC');

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login($usernameBQ, $passwordBQ))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Verifica se página existe
$page = $wiki->getPage("Wikipédia:Pedidos/Proteção");
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

	//Recupera nome da página
	$alvo = trim($lines['0'], "= ");

	//Coleta informações da página
	$info = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=logevents&letype=protect&lelimit=1&letitle=".urlencode($alvo)), true)['query']['logevents'];

	//Caso não exista log de proteção, interrompe loop e segue para a próxima seção
	if (!isset($info[0])) {
		continue;
	} else {
		$protectinfo = $info[0];
	}

	//Coleta timestamp da requesição
	preg_match_all('/(\d{1,2})h(\d{1,2})min de (\d{1,2}) de ([^ ]*) de (\d{1,4}) \(UTC\)/', $sections[$i], $timestamp);

	//Converte nome do mês em extenso para número
	$m = $timestamp['4']['0'];
		if ($m == "janeiro") 	{$m = "01";}
	elseif ($m == "fevereiro") 	{$m = "02";}
	elseif ($m == "março") 		{$m = "03";}
	elseif ($m == "abril") 		{$m = "04";}
	elseif ($m == "maio") 		{$m = "05";}
	elseif ($m == "junho") 		{$m = "06";}
	elseif ($m == "julho") 		{$m = "07";}
	elseif ($m == "agosto") 	{$m = "08";}
	elseif ($m == "setembro") 	{$m = "09";}
	elseif ($m == "outubro") 	{$m = "10";}
	elseif ($m == "novembro") 	{$m = "11";}
	elseif ($m == "dezembro") 	{$m = "12";}
	else {die("Erro no nome do mês");}

	//Gera timestamp da requesição compatível com o obtido pelo API
	$timestamp_req = $timestamp['5']['0']."-".$m."-".$timestamp['3']['0']."T".$timestamp['1']['0'].":".$timestamp['2']['0'].":00Z";

	//Verifica se proteção foi posterior a requesição
	if (strtotime($timestamp_req) > strtotime($protectinfo['timestamp'])) continue;

	//Substitui seção inicial
	$sections[$i] = preg_replace('/<!--\n?{{Respondido[^>]*>/', '{{Respondido2|feito|texto=', $sections[$i]);

	$sub1 = array(
		"[", 
		"edit=", 
		"move=", 
		"create=", 
		"autoconfirmed", 
		"extendedconfirmed", 
		"editautoreviewprotected", 
		"sysop"
	);
	$sub2 = array(
		"\n:*[", 
		"Editar: ", 
		"Mover: ", 
		"Criar: ", 
		"[[Ficheiro:Wikipedia_Autoconfirmed.svg|20px]] [[Wikipédia:Autoconfirmados|Autoconfirmado]]", 
		"[[Ficheiro:Usuario_Autoverificado.svg|20px]] [[Wikipédia:Autoconfirmados estendidos|Autoconfirmados estendidos]]", 
		"[[Ficheiro:Wikipedia_Autopatrolled.svg|20px]] [[Wikipédia:Autorrevisores|Autorrevisor]]", 
		"[[Ficheiro:Wikipedia_Administrator.svg|20px]] [[Wikipédia:Administradores|Administrador]]");

	//Substitui seção final
	$sections[$i] = preg_replace(
		'/<!--:{{proteção[^>]*>/', 
		":{{subst:feito|Feito}}. Proteção realizada em <span class='plainlinks'>[https://pt.wikipedia.org/w/index.php?type=protect&title=Especial:Registo&page=".urlencode($alvo)."&user=".urlencode($protectinfo['user'])." ".utf8_encode(strftime('%Hh%Mmin de %d de %B de %Y', strtotime($protectinfo['timestamp'])))." (UTC)]</span> por [[User:".$protectinfo['user']."|".$protectinfo['user']."]] com o(s) seguinte(s) parâmetro(s):".str_replace($sub1, $sub2, $protectinfo['params']['description'])."\n:--[[User:BloqBot|BloqBot]] <small>~~~~~</small>}}", 
		$sections[$i]
	);

	//Grava seção
	if ($page->setText($sections[$i], $i, true, "bot: Fechando pedido cumprido")) {
		echo "Gravando ".$alvo."<br>";
	} else {
		$error = $page->getError();
		echo "<hr>Error: ".print_r($error, true)."\n";
	}	
}