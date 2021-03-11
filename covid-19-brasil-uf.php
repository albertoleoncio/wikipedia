<?php
include './bin/globals.php';

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Recupera dados da predefinição
$page = $wiki->getPage('Predefinição:Números de casos de COVID-19 por Unidade Federativa no Brasil/Externo');
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getText();

//Regex - captura total anterior
preg_match_all('/confirmados\|([0-9]*)/', $wikiCode, $output_anterior);
$anterior = $output_anterior[1][0];

//Recupera dados da fonte e transforma em uma array
$headers = array(
	'User-agent: Mozilla/5.0 (compatible, wikimediacloud.org) https://pt.wikipedia.org/wiki/User:AlbeROBOT',
	'Authorization: Token '.$BrasilIOToken
);
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://api.brasil.io/v1/dataset/covid19/caso/data/?format=json&is_last=True&place_type=state',
    CURLOPT_HTTPHEADER => $headers
]);
$ref = curl_exec($curl);
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
    $linha = "-->{{#ifeq:{{{1}}}|".$UFs[1][$x]."-c|".$UFs[2][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{1}}}|confirmados|".$UFs[2][1]."|}}<!--\n";

for ($x = 2; $x < 29; $x++) {
    $linha = "-->{{#ifeq:{{{1}}}|".$UFs[1][$x]."-o|".$UFs[3][$x]."|}}<!--\n";
    $saida = $saida.$linha;
}
$saida = $saida."-->{{#ifeq:{{{1}}}|obitos|".$UFs[3][1]."|}}<!--\n";

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