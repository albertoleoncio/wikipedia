<?php
echo "<pre>";
include './bin/globals.php';

//Login
include './bin/api.php';
loginAPI($username, $password);

//Recupera dados da predefinição
$page = 'Predefinição:Números de casos de COVID-19 por Unidade Federativa no Brasil/Consórcio';
$wikiCode = getAPI($page);

//Regex - captura total anterior
preg_match_all('/confirmados\|([0-9]*)/', $wikiCode, $output_anterior);
$anterior = $output_anterior[1][0];

//Recupera dados da fonte e transforma em uma array
$urlfonte = "https://infogbucket.s3.amazonaws.com/google_planilhas/corona-casos-brasil/corona-casos-brasil.json";
$ref = @file_get_contents($urlfonte);
$dados = json_decode($ref, true);

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
foreach ($dados as $linha) {
	$UFs[1][$x] = $estados[$linha['local']];
	$UFs[2][$x] = $linha['casosConfirmados'];
	$UFs[3][$x] = $linha['mortes'];
	$UFs[2][1] = $UFs[2][1] + $linha['casosConfirmados'];
	$UFs[3][1] = $UFs[3][1] + $linha['mortes'];
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
editAPI($wikiCode, 0, true, "bot: Atualizando estatísticas", $page);