<?php
include './bin/globals.php';

////////////////////////////////////////////////////////////////////////////////////////
//
//	Coleta de dados do site do Ministério da Saúde
//
////////////////////////////////////////////////////////////////////////////////////////

//Recupera dados da fonte - Adaptado de https://github.com/wcota/covid19br/blob/master/scrape-covid-saude-gov-br.sh
$headers = array(
    'X-Parse-Application-Id: unAFkcaNDeXajurGB7LChj8SgQYS2ptm',
    'TE: Trailers',
);
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalSinteseSep',
    CURLOPT_HTTPHEADER => $headers
]);
$result = curl_exec($curl);
$PortalSintese = json_decode($result, true);

$curl2 = curl_init();
curl_setopt_array($curl2, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalEstado',
    CURLOPT_HTTPHEADER => $headers
]);
$results = curl_exec($curl2);
$PortalEstado = json_decode($results, true);

//Formação dos campos de dados gerais
$confirmado = str_replace(".", "", $PortalSintese[0]['casosAcumuladoN']);
$obitos = str_replace(".", "", $PortalSintese[0]['obitosAcumuladoN']);
$recuperados = str_replace(".", "", $PortalSintese[0]['Recuperadosnovos']);
$timestamp = strtotime($PortalSintese[0]['updated_at']);

//Proteção contra erro
if (!isset($confirmado) OR $confirmado == FALSE) die("Erro no total de confirmados");

//Construção do wikitexto dos dados gerais
$saida =  "\n-->{{#ifeq:{{{1}}}|confirmados|".$confirmado."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|obitos|".$obitos."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|recuperados|".$recuperados."|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|data|{{DataExt|".date("d|m|Y",$timestamp)."}}|}}<!--\n".
			"-->{{#ifeq:{{{1}}}|hora|".date("H:i",$timestamp)."|}}<!--\n";

//Ordenar unidades federativas por ordem alfabética
usort($PortalEstado, function ($a, $b) {
	return $a['_id'] <=> $b['_id'];
});

//Loop para montar a array das UFs
$UFs = array();
$x = 0;
foreach ($PortalEstado as $linha) {
	$UFs[1][$x] = $linha['_id'];
	$UFs[2][$x] = $linha['casosAcumulado'];
	$UFs[3][$x] = $linha['obitosAcumulado'];
	$x++;
}

////////////////////////////////////////////////////////////////////////////////////////
//
//	Atualiza dados da predefinição
//
////////////////////////////////////////////////////////////////////////////////////////

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Recupera dados da predefinição
$page = $wiki->getPage('Predefinição:Números de casos de COVID-19 por Unidade Federativa no Brasil/Ministério da Saúde');
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getText();

//Loop para construção do wikitexto das UFs
for ($y = 0; $y < $x; $y++) {
    $linha = "-->{{#ifeq:{{{1}}}|".$UFs[1][$y]."-c|".$UFs[2][$y]."|}}<!--\n-->{{#ifeq:{{{1}}}|".$UFs[1][$y]."-o|".$UFs[3][$y]."|}}<!--\n";
    $saida = $saida.$linha;
}

//Substituição do código antigo da predefinição pelo código novo
$pieces = explode("%", $wikiCode);
$pieces[1] = $saida;
$wikiCode = implode("%", $pieces);

//Gravar código
if ($page->setText($wikiCode, 0, true, "bot: Atualizando estatísticas")) {
	echo "\nEdição em predefinição realizada.\n";
} else {
	$error = $page->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

////////////////////////////////////////////////////////////////////////////////////////
//
//	Atualiza dados do Gráfico
//
////////////////////////////////////////////////////////////////////////////////////////

//Formata string de dia compatível com predefinição 
$dia_atualização = date("d-m-Y",$timestamp);

//Formata string de nova linha para atualização
$nova_linha = $dia_atualização.";".$obitos.";".$recuperados.";".$confirmado."";

//Recupera dados da predefinição
$page2 = $wiki->getPage('Predefinição:Dados da pandemia de COVID-19/Gráfico de casos médicos no Brasil');
if (!$page2->exists()) die('Page not found');

//Converte página em array, separando por linhas
$código = explode("\n", $page2->getText());

//Procura linha que consta após lista de dias na predefinição
$key = array_search("|caption='''Fontes:'''", $código);

//Identifica última atualização da predefinição
$ultima_atualização = substr($código[$key-1], 0, 10);

//Verifica se o dia da atualização corresponde a última atualização na predefinição
//Caso não corresponda, insere a nova linha. Caso corresponda, verifica a última atualização
//Caso seja diferente, corrige-a com os dados atualizados
if ($dia_atualização != $ultima_atualização) {
	array_splice($código, $key, 0, $nova_linha);
} else {
	if ($código[$key-1] != $nova_linha) $código[$key-1] = $nova_linha;
}

//Remonta código
$código = implode("\n", $código);

//Gravar código
if ($page2->setText($código, 0, true, "bot: Atualizando estatísticas")) {
	echo "\nGráfico atualizado.\n";
} else {
	$error = $page2->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

?>