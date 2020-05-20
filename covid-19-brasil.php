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
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalSintese',
    CURLOPT_HTTPHEADER => $headers
]);
$result = curl_exec($curl);
$PortalSintese = json_decode($result, true);

//Formação dos campos de dados gerais
$confirmado = str_replace(".", "", $PortalSintese[0]['casosAcumuladoN']);
$obitos = str_replace(".", "", $PortalSintese[0]['obitosAcumuladoN']);
$recuperados = str_replace(".", "", $PortalSintese[0]['Recuperadosnovos']);
$datahora = explode("T", $PortalSintese[0]['updated_at']);
$horacompleta = explode(":", $datahora[1]);
$horacompleta[0] = $horacompleta[0]-3;
$hora = $horacompleta[0].":".$horacompleta[1];
$datacompleta = explode("-", $datahora[0]);
$dia = $datacompleta[2];
$mes = $datacompleta[1];
$ano = $datacompleta[0];

//Construção do wikitexto dos dados gerais
$saida = "\n"."-->{{#ifeq:{{{1}}}|confirmados|".$confirmado."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|confirmados-o|<!-- ÓBITOS -->".$obitos."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|recuperados|".$recuperados."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|data|{{DataExt|".$dia."|".$mes."|".$ano."}}|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|hora|".$hora."|}}<!--\n";

//Merge das arrays das regiões brasileiras
$SinteseUFs = array_merge($PortalSintese[1]['listaMunicipios'], $PortalSintese[2]['listaMunicipios'], $PortalSintese[3]['listaMunicipios'], $PortalSintese[4]['listaMunicipios'], $PortalSintese[5]['listaMunicipios']);

//Ordenar unidades federativas por ordem alfabética
usort($SinteseUFs, function ($a, $b) {
	return $a['_id'] <=> $b['_id'];
});

//Loop para montar a array das UFs
$UFs = array();
$x = 0;
foreach ($SinteseUFs as $linha) {
	$UFs[1][$x] = $linha['_id'];
	$UFs[2][$x] = $linha['casosAcumulado'];
	$UFs[3][$x] = $linha['obitosAcumulado'];
	$x++;
}

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