<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'globals.php';

//Recupera dados da fonte
$url = "https://en.wikipedia.org/w/index.php?title=Template:2019%E2%80%9320_coronavirus_pandemic_data&action=raw";
$html = @file_get_contents($url);

//Separa paises e insere em um array
$htmle = explode("|-", $html);

//Predefine $resultado como uma array
$resultado = array();

//Lista de substituição
$de = " March 2020";
$para = "-03-2020"; 

//Loop para processar cada item da array
for ($x = 0; $x < count($htmle); $x++) {

	//Verifica se item possui "flagdeco", indicando que a string se refere a um país
	if (strpos($htmle[$x], 'flagdeco') !== false) {

		//Separa a string em substrings, baseado na marcação de estilo da tabela
		$result = preg_split('/\| *?style="padding:0px 2px;" *?\| ?/', $htmle[$x]);

		//Separa o nome do país e insere na array de resultado
		preg_match_all('/{{flagdeco\|([^}]*)}}/', $result[0], $array1);
		$resultado[$x][0] = $array1[1][0];

		//Separa informações numéricas e insere na array
		preg_match_all('/\| *?style="padding:0px 2px;" *?\| *?([^\n]*)/', $htmle[$x], $array2);
		$resultado[$x][1] = trim($array2[1][0]);
		$resultado[$x][2] = trim($array2[1][1]);
		$resultado[$x][3] = trim($array2[1][2]);

		//Processa a fonte e insere na array
		$resultado[$x][4] = str_replace($de, $para, preg_replace('/date=([0-9]{4})-([0-9]{2})-([0-9]{2})/', 'date=$3-$2-$1', trim($result[4])));

	} else {

		//Removendo item, já que não se trata de um país
		unset($htmle[$x]);
	}
}

//Reorganiza array
$output = array_values($resultado);

//Monta saida para inserir na página
$saida = "<!--\n";
for ($x = 0; $x < count($output); $x++) {
    $linha ="-->{{#ifeq:{{{1}}}|".$output[$x][0]."-C|{{fmtn|".preg_replace('/,/', '', $output[$x][1])."}}|}}<!--\n".
    		"-->{{#ifeq:{{{1}}}|".$output[$x][0]."-M|{{fmtn|".preg_replace('/,/', '', $output[$x][2])."}}|}}<!--\n".
      		"-->{{#ifeq:{{{1}}}|".$output[$x][0]."-S|{{fmtn|".preg_replace('/,/', '', $output[$x][3])."}}|}}<!--\n".
    		"-->{{#ifeq:{{{1}}}|".$output[$x][0]."-F|".$output[$x][4]."|}}<!--\n";
    $saida = $saida.$linha;
}

//Regex - captura total anterior
preg_match_all('/! class="covid-total-row"[^\']*\'\'\'([^\']*)/', $html, $total);
$saida = $saida."-->{{#ifeq:{{{1}}}|paises-C|{{fmtn|".preg_replace('/,/', '', $total[1][1])."}}|}}<!--\n".
    			"-->{{#ifeq:{{{1}}}|paises-M|{{fmtn|".preg_replace('/,/', '', $total[1][2])."}}|}}<!--\n".
      			"-->{{#ifeq:{{{1}}}|paises-S|{{fmtn|".preg_replace('/,/', '', $total[1][3])."}}|}}<!--\n".
      			"-->{{#ifeq:{{{1}}}|paises-P|{{fmtn|".preg_replace('/,/', '', $total[1][0])."}}|}}\n";

/*$saida = "{| class='wikitable'\n|+\n!Local\n!Casos\n!Mortes\n!Recuperados\n!Fonte\n";
for ($x = 0; $x < count($output); $x++) {
    $linha = "|-\n|".$output[$x][0]."\n|".preg_replace('/,/', '', $output[$x][1])."\n|".preg_replace('/,/', '', $output[$x][2])."\n|".preg_replace('/,/', '', $output[$x][3])."\n|".$output[$x][4]."\n";
    $saida = $saida.$linha;
}
$saida = $saida."|}";*/

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
$page = $wiki->getPage('Predefinição:Dados da pandemia de COVID-19/wikien');
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getText();

//Gravar código
if ($page->setText($saida, 0, true, "bot: Atualizando estatísticas")) {
	echo "\nEdição realizada.\n";
} else {
	$error = $page->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

?>
