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

//Recupera dados da fonte e transforma em uma array
$urlfonte = "https://brasil.io/api/dataset/covid19/caso/data?is_last=True&place_type=state";
$ref = @file_get_contents($urlfonte);
$dados = json_decode($ref, true)['results'];

//Reordena de acordo com as UFs
usort($dados, function ($a, $b) {
    return $a['state'] <=> $b['state'];
});

//Loop para montar a array das UFs
$UFs = array();
$UFs[1][1] = 'confirmados';
$UFs[2][1] = 0;
$UFs[3][1] = 0;
$x = 2;
foreach ($dados as $linha) {
	$UFs[1][$x] = $linha['state'];
	$UFs[2][$x] = $linha['confirmed'];
	$UFs[3][$x] = $linha['deaths'];
	$UFs[2][1] = $UFs[2][1] + $linha['confirmed'];
	$UFs[3][1] = $UFs[3][1] + $linha['deaths'];
	$x++;
}

//Processa as linhas para inserir na predefinição
$saida = "\n";
for ($x = 2; $x < 29; $x++) {
    $linha = "-->{{#ifeq:{{{2}}}|".$UFs[1][$x]."-UF|".$UFs[2][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{2}}}|confirmados-UF|".$UFs[2][1]."{{#ifeq:{{{ref}}}|sim|<ref name=\"casos confirmados - estaduais\">{{citar web|URL=http://brasil.io/dataset/covid19/caso|titulo=COVID-19 - Datasets - Brasil.IO|data=2020-04-".date("d")."|acessodata=2020-04-".date("d")."  |ultimo=Brasil.IO|lingua=pt-br}}</ref>|}}|}}<!--\n";

for ($x = 2; $x < 29; $x++) {
    $linha = "-->{{#ifeq:{{{2}}}|".$UFs[1][$x]."-o-UF|".$UFs[3][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{2}}}|confirmados-o-UF|".$UFs[3][1]."{{#ifeq:{{{ref}}}|sim|<ref name=\"casos confirmados - estaduais\"/>|}}|}}<!--\n";

//Retorna valores totais para simples conferência
echo "Total anterior = ".$anterior."<br>Total atual = ".$UFs[2][1].".<br>";

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
