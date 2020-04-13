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
	echo '<pre>Login OK<br>' ;
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

//Recupera dados do link da referência
$dia = date("d");
$urlfonte = "https://g1.globo.com/bemestar/coronavirus/noticia/2020/04/".$dia."/casos-de-coronavirus-no-brasil-em-".ltrim($dia,"0")."-de-abril.ghtml";
$ref = @file_get_contents($urlfonte);
if ($ref == false) {
	$dia = date("d", strtotime("-1 day"));
	$urlfonte = "https://g1.globo.com/bemestar/coronavirus/noticia/2020/04/".$dia."/casos-de-coronavirus-no-brasil-em-".ltrim($dia,"0")."-de-abril.ghtml";
	$ref = @file_get_contents($urlfonte);
	if ($ref == false) {
		die("Fonte não disponível.");
	}
}

//Recupera dados da fonte
$url = "https://datawrapper.dwcdn.net/M2Eyg/";
$html = @file_get_contents($url);
$size = strlen($html);

//Loop para escapar dos redirecionamentos
while ($size < 100) {
	preg_match('/url=..\/..\/.{5}\/(.*)"/', $html, $redirect);
	$urlget = $url.$redirect[1];
	$html = @file_get_contents($urlget);
	$size = strlen($html);
}

//Regex para isolar JSON de dados da página
preg_match_all('/chartData: \"*(.*)\"\,\n/', $html, $chartData);

//Regex para isolar dados das UFs
preg_match_all('/\n([^;]*);([0-9]*);([0-9]*)/', json_decode('{"1":"'.$chartData[1][0].'"}', true)[1], $dados);

//Constroi array para conversão de nomes dos estados para siglas
$estados = array_flip(array(
"AC"=>"Acre",
"AL"=>"Alagoas",
"AM"=>"Amazonas",
"AP"=>"Amapá",
"BA"=>"Bahia",
"CE"=>"Ceará",
"DF"=>"Distrito Federal",
"ES"=>"Espírito Santo",
"GO"=>"Goiás",
"MA"=>"Maranhão",
"MT"=>"Mato Grosso",
"MS"=>"Mato Grosso do Sul",
"MG"=>"Minas Gerais",
"PA"=>"Pará",
"PB"=>"Paraíba",
"PR"=>"Paraná",
"PE"=>"Pernambuco",
"PI"=>"Piauí",
"RJ"=>"Rio de Janeiro",
"RN"=>"Rio Grande do Norte",
"RO"=>"Rondônia",
"RS"=>"Rio Grande do Sul",
"RR"=>"Roraima",
"SC"=>"Santa Catarina",
"SE"=>"Sergipe",
"SP"=>"São Paulo",
"TO"=>"Tocantins"));

//Loop para montar a array das UFs
$UFs = array();
$UFs[1][1] = 'confirmados';
$UFs[2][1] = 0;
$UFs[3][1] = 0;
$x = 2;

foreach ($dados[0] as $linha) {
	$linha = explode(";", $linha);
	$UFs[1][$x] = $estados[trim($linha[0])];
	$UFs[2][$x] = $linha[1];
	$UFs[3][$x] = $linha[2];
	$UFs[2][1] = $UFs[2][1] + $linha[1];
	$UFs[3][1] = $UFs[3][1] + $linha[2];
	$x++;
}

//Processa as linhas para inserir na predefinição
$saida = "\n";
for ($x = 2; $x < 29; $x++) {
    $linha = "-->{{#ifeq:{{{2}}}|".$UFs[1][$x]."-UF|".$UFs[2][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{2}}}|confirmados-UF|".$UFs[2][1]."{{#ifeq:{{{ref}}}|sim|<ref name=\"casos confirmados - estaduais\">{{citar web|URL=".$urlfonte."|titulo=Casos de coronavírus no Brasil em ".$dia." de abril |data=2020-04-".$dia." |acessodata=2020-04-".$dia."  |ultimo=G1 |autorlink=G1 |lingua=pt-br}}</ref>|}}|}}<!--\n";

for ($x = 2; $x < 29; $x++) {
    $linha = "-->{{#ifeq:{{{2}}}|".$UFs[1][$x]."-o-UF|".$UFs[3][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{2}}}|confirmados-o-UF|".$UFs[3][1]."{{#ifeq:{{{ref}}}|sim|<ref name=\"casos confirmados - estaduais\"/>|}}|}}<!--\n";

echo "Total anterior = ".$anterior."<br>Total atual = ".$UFs[2][1].".<br>";
if ($UFs[2][1] == "0") {
	die("Erro. Valor total zerado");
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