<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'globals.php';
date_default_timezone_set('America/Bahia');
$Portal = array();
echo "<pre>";

//Recupera dados da fonte - Adaptado de https://github.com/wcota/covid19br/blob/master/scrape-covid-saude-gov-br.sh
$headers = array(
    'X-Parse-Application-Id: unAFkcaNDeXajurGB7LChj8SgQYS2ptm',
    'TE: Trailers',
);
$curlGeral = curl_init();
curl_setopt_array($curlGeral, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalGeral',
    CURLOPT_HTTPHEADER => $headers
]);
$result = curl_exec($curlGeral);
$Portal['Geral'] = json_decode($result, true);

$curlMapa = curl_init();
curl_setopt_array($curlMapa, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalMapa',
    CURLOPT_HTTPHEADER => $headers
]);
$resultMapa = curl_exec($curlMapa);
$Portal['Mapa'] = json_decode($resultMapa, true);

$curlSemana = curl_init();
curl_setopt_array($curlSemana, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalSemana',
    CURLOPT_HTTPHEADER => $headers
]);
$resultSemana = curl_exec($curlSemana);
$Portal['Semana'] = json_decode($result, true);

$curlDias = curl_init();
curl_setopt_array($curlDias, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalDias',
    CURLOPT_HTTPHEADER => $headers
]);
$resultDias = curl_exec($curlDias);
$Portal['Dias'] = json_decode($resultDias, true);

$curlRegiao = curl_init();
curl_setopt_array($curlRegiao, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalRegiao',
    CURLOPT_HTTPHEADER => $headers
]);
$resultRegiao = curl_exec($curlRegiao);
$Portal['Regiao'] = json_decode($resultRegiao, true);

$curlAcumulo = curl_init();
curl_setopt_array($curlAcumulo, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalAcumulo',
    CURLOPT_HTTPHEADER => $headers
]);
$resultAcumulo = curl_exec($curlAcumulo);
$Portal['Acumulo'] = json_decode($resultAcumulo, true);

print_r($Portal);
