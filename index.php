<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'globals.php';
date_default_timezone_set('America/Bahia');

//Login
$api_url = 'https://pt.wikipedia.org/w/api.php';
include 'credenciais.php';
$wiki = new Wikimate($api_url);
if ($wiki->login($username, $password))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Recupera dados da predefinição
$page = $wiki->getPage('Predefinição:Número de casos de COVID-19/Brasil-UF/quantidade');
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getText();

//Regex - captura total anterior
preg_match_all('/confirmados-UF\|([0-9]*)/', $wikiCode, $output_anterior);
$anterior = $output_anterior[1][0];

//Recupera dados do link da fonte
$dia = date("d");
$url = "https://g1.globo.com/bemestar/coronavirus/noticia/2020/03/".$dia."/casos-de-coronavirus-no-brasil-em-".$dia."-de-marco.ghtml";
$html = @file_get_contents($url);
if ($html == false) {
	$dia = $dia-1;
	$url = "https://g1.globo.com/bemestar/coronavirus/noticia/2020/03/".$dia."/casos-de-coronavirus-no-brasil-em-".$dia."-de-marco.ghtml";
	$html = @file_get_contents($url);
	if ($html == false) {
		die("Fonte não disponível.");
	}
}
unset($html);

//Recupera dados da fonte real
$urlfonte = "https://www.datawrapper.de/_/eXkcW/";
$html = @file_get_contents($urlfonte);

//Regex para isolar o JSON e transforma em array
preg_match_all('/id="chart-data">(.*)<\/script><script/', $html, $json);
$array = json_decode($json[1][0], true);

//Isola trecho do log de mudanças e insere em uma array
$changes = $array['metadata']['data']['changes'];

//Insere valores de base no array final
$UFs = array();
$UFs[1][1] = 'confirmados';	
$UFs[1][2] = 'AC';	
$UFs[1][3] = 'AL';	
$UFs[1][4] = 'AP';	
$UFs[1][5] = 'AM';	
$UFs[1][6] = 'BA';	
$UFs[1][7] = 'CE';	
$UFs[1][8] = 'DF';	
$UFs[1][9] = 'ES';	
$UFs[1][10] = 'GO';	
$UFs[1][11] = 'MA';	
$UFs[1][12] = 'MT';	
$UFs[1][13] = 'MS';	
$UFs[1][14] = 'MG';	
$UFs[1][15] = 'PA';	
$UFs[1][16] = 'PB';	
$UFs[1][17] = 'PR';	
$UFs[1][18] = 'PE';	
$UFs[1][19] = 'PI';	
$UFs[1][20] = 'RJ';	
$UFs[1][21] = 'RN';	
$UFs[1][22] = 'RS';	
$UFs[1][23] = 'RO';	
$UFs[1][24] = 'RR';	
$UFs[1][25] = 'SC';	
$UFs[1][26] = 'SP';	
$UFs[1][27] = 'SE';	
$UFs[1][28] = 'TO';
$UFs[2][1] = 3475;
$UFs[2][2] = 25;
$UFs[2][3] = 12;
$UFs[2][4] = 2;
$UFs[2][5] = 81;
$UFs[2][6] = 123;
$UFs[2][7] = 282;
$UFs[2][8] = 240;
$UFs[2][9] = 54;
$UFs[2][10] = 49;
$UFs[2][11] = 14;
$UFs[2][12] = 11;
$UFs[2][13] = 28;
$UFs[2][14] = 189;
$UFs[2][15] = 16;
$UFs[2][16] = 10;
$UFs[2][17] = 125;
$UFs[2][18] = 57;
$UFs[2][19] = 9;
$UFs[2][20] = 493;
$UFs[2][21] = 28;
$UFs[2][22] = 197;
$UFs[2][23] = 6;
$UFs[2][24] = 12;
$UFs[2][25] = 163;
$UFs[2][26] = 1223;
$UFs[2][27] = 16;
$UFs[2][28] = 9;

//Edita a array final com a array de mudanças
foreach ($changes as $change) {
	$UFs[2][$change['column']] = $change['value'];
}

//Processa as linhas para inserir na predefinição
$saida = "\n";
for ($x = 2; $x < 29; $x++) {
    $linha = "-->{{#ifeq:{{{2}}}|".$UFs[1][$x]."-UF|".$UFs[2][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{2}}}|confirmados-UF|".$UFs[2][1]."{{#ifeq:{{{ref}}}|sim|<ref name=\"casos confirmados - estaduais\">{{citar web|URL=".$url."|título=G1}}</ref>|}}|}}<!--\n";

//Compara se há diferença entre os totais
if ($anterior == $UFs[2][1]) {
	die("Total igual (".$anterior."). Nada a fazer.");
} else {
	echo "Total anterior = ".$anterior."<br>Total atual = ".$UFs[2][1].".<br>";
}

//Substituição do código antigo da predefinição pelo código novo
$pieces = explode("%", $wikiCode);
$pieces[1] = $saida;
$wikiCode = implode("%", $pieces);

//Gravar código
if ($page->setText($wikiCode, 0, true, "bot: Atualizando estatísticas")) {
	echo "\nEdição realizada.\n";
} else {
	$error = $page->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

?>
