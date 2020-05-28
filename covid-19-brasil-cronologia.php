<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'globals.php';
date_default_timezone_set('America/Bahia');

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

$CsvString = @file_get_contents($PortalGeral['results']['0']['arquivo']['url']);
@unlink(__DIR__ . '/var/input/current.csv');
$fp = fopen(__DIR__ . '/var/input/current.csv','w+');
fwrite($fp, $CsvString);
fclose($fp);

// https://github.com/hagnat/covid
require_once __DIR__ . '/src/Application/ParserInterface.php';
require_once __DIR__ . '/src/Domain/ReportedCase.php';
require_once __DIR__ . '/src/Domain/ReportedCases.php';
require_once __DIR__ . '/src/Infrastructure/CovidCsvReader.php';
require_once __DIR__ . '/src/Infrastructure/MediaWiki/PortugueseTable.php';
use App\Application\CovidTableGenerator;
use App\Infrastructure\CovidCsvReader;
use App\Infrastructure\MediaWiki\PortugueseTable as MediawikiPortugueseTable;
$covidCsvReader = new CovidCsvReader();
$cases = $covidCsvReader->read(__DIR__ . '/var/input/current.csv');
$portugueseParser = new MediawikiPortugueseTable();
$wikiCode = $portugueseParser->parse($cases);

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
$page = $wiki->getPage('Predefinição:Casos de COVID-19 no Brasil');
if (!$page->exists()) die('Page not found');

//Gravar código
if ($page->setText($wikiCode, 0, true, "bot: Atualizando estatísticas")) {
	echo "\nEdição realizada.\n";
} else {
	$error = $page->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

?>