<?php
include 'globals.php';
date_default_timezone_set('America/Bahia');

//Login
$api_url = 'http://pt.wikipedia.org/w/api.php';
$username = 'AlbeROBOT';
$password = 'password';
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

//Recupera dados da fonte
$url = "https://g1.globo.com/bemestar/coronavirus/noticia/2020/03/".date("d")."/casos-de-coronavirus-no-brasil-em-".date("d")."-de-marco.ghtml";
$html = file_get_contents($url);

//Regex - extrai dados da fonte e insere em uma array
preg_match_all('/<td>([A-Z]{2})<\/td> <td>([0-9]*)/', $html, $UFs);
$saida = "\n";
for ($x = 0; $x < count($UFs[1]); $x++) {
    $linha = "-->{{#ifeq:{{{2}}}|".$UFs[1][$x]."-UF|".$UFs[2][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
preg_match_all('/<td>Total<\/td> <td>([0-9]*)/', $html, $total);
$saida = $saida."-->{{#ifeq:{{{2}}}|confirmados-UF|".$total[1][0]."{{#ifeq:{{{ref}}}|sim|<ref name=\"casos confirmados - estaduais\">{{citar web|URL=".$url."|título=G1}}</ref>|}}|}}<!--\n".time()."\n";

//Compara se há diferença entre os totais
if ($anterior == $total[1][0]) {
	die('Total igual. Nada a fazer.');
} else {
	echo "Total anterior = ".$anterior."<br>Total atual = ".$total[1][0]."<br>";
}

//Substituição do código antigo da predefinição pelo código novo
$pieces = explode("%", $wikiCode);
$pieces[1] = $saida;
$wikiCode = implode("%", $pieces);

//Output - código para ser gravado na predefinição
echo '<textarea rows="4" cols="50">'.$wikiCode."</textarea><br>";

//Gravar código
if ($page->setText($wikiCode)) {
	echo "\n'Edição realizada.\n";
} else {
	$error = $page->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

?>
