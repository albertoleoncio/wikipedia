<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'globals.php';
date_default_timezone_set('America/Bahia');

//Recupera dados da fonte - Adaptado de https://github.com/wcota/covid19br/blob/master/scrape-covid-saude-gov-br.sh
$headers = array(
    'X-Parse-Application-Id: unAFkcaNDeXajurGB7LChj8SgQYS2ptm',
    'TE: Trailers',
);
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalGeral',
    CURLOPT_HTTPHEADER => $headers
]);
$result = curl_exec($curl);
$PortalGeral = json_decode($result, true);

$curl2 = curl_init();
curl_setopt_array($curl2, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalMapa',
    CURLOPT_HTTPHEADER => $headers
]);
$result2 = curl_exec($curl2);
$PortalMapa = json_decode($result2, true);
//var_dump($PortalGeral['results'][0]);
//var_dump($PortalMapa['results']);

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
$x = 1;
foreach ($PortalMapa['results'] as $linha) {
	$UFs[1][$x] = $estados[trim($linha['nome'])];
	$UFs[2][$x] = $linha['qtd_confirmado'];
	$UFs[3][$x] = $linha['qtd_obito'];
	$x++;
}

//Formação dos campos de dados gerais
$confirmado = str_replace(".", "", $PortalGeral['results'][0]['total_confirmado']);
$obitos = str_replace(".", "", $PortalGeral['results'][0]['total_obitos']);
$datahora = explode(" ", $PortalGeral['results'][0]['dt_atualizacao']);
$hora = $datahora[0];
$datacompleta = explode("/", $datahora[1]);
$dia = $datacompleta[0];
$mes = $datacompleta[1];
$ano = $datacompleta[2];

//Construção do wikitexto dos dados gerais
$saida = "\n"."-->{{#ifeq:{{{1}}}|confirmados|".$confirmado."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|confirmados-o|<!-- ÓBITOS -->".$obitos."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|data|{{DataExt|".$dia."|".$mes."|".$ano."}}|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|hora|".$hora."|}}<!--\n";

//Loop para construção do wikitexto das UFs
for ($y = 1; $y < $x; $y++) {
    $linha = "-->{{#ifeq:{{{1}}}|".$UFs[1][$y]."|".$UFs[2][$y]."|}}<!--\n-->{{#ifeq:{{{1}}}|".$UFs[1][$y]."-o|".$UFs[3][$y]."|}}<!--\n";
    $saida = $saida.$linha;
}

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
$page = $wiki->getPage('Predefinição:Número de casos de COVID-19/Brasil');
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getText();

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