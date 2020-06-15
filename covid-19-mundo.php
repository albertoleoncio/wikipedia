<?php

//Recupera dados da fonte
$url = "https://en.wikipedia.org/w/index.php?title=Template:COVID-19_pandemic_data&action=raw";
$html = @file_get_contents($url);
echo "<b>Processamento da Template:COVID-19_pandemic_data</b>\n";

//Separa paises e insere em um array
$htmle = explode("\n|-", $html);

//Predefine $resultado e $wikien como uma array
$resultado = array();
$wikien = array();

//Lista de substituição
$de = array(" June 2020"," July 2020"," March 2020"," April 2020"," May 2020","|url-status=live");
$para = array("-06-2020","-07-2020","-03-2020","-04-2020","-05-2020",""); 

//Loop para processar cada item da array
for ($x = 0; $x < count($htmle); $x++) {

	//Verifica se item possui marcação do arquivo da bandeira, indicando que a string se refere a um país
	if (strpos($htmle[$x], '[[File:Flag') !== false) {

		//Separa a string em substrings, baseado na marcação de estilo da tabela
		$result = preg_split('/\n *\|/', $htmle[$x]);

		//Separa o nome do país, elimina predefinições como as marcas de rodapé e insere na array de resultado como uma key
		preg_match_all('/! ?scope="row" ?(?:data-sort-value="[^"]*" ?)?\| ?\'{0,2}\[\[[^F][^\|]*\|([^\|]*)]]/', preg_replace('/{{[^}]*}}|<[^>]*>|\([^\)]*\)/', '', $result[0]), $array1);
		echo @$array1[1][0]."...";
		$array1[1][0] = @trim($array1[1][0]);

		//Insere nome do país na lista do relatório
		array_push($wikien, $array1[1][0]);

		//Insere o nome do país como um valor na array de resultado
		$resultado[$array1[1][0]][0] = $array1[1][0];

		//Conta o numero de strings dentro da array
		$numitens = count($result);

		//Confere se existe uma diferença entre a quantidade de chaves "}{", o que indica que a fonte está dividida em duas strings
		$abrechave = substr_count($result[$numitens-1], '{');
		$fechachave = substr_count($result[$numitens-1], '}');
		while ($abrechave !== $fechachave) {
			//Concatena as duas ultimas strings, elimina a última e subtrai 1 na contagem de strings da array
			$result[$numitens-2] = $result[$numitens-2].'|'.$result[$numitens-1];
			unset($result[$numitens-1]);
			$numitens--;
			$abrechave = substr_count($result[$numitens-1], '{');
			$fechachave = substr_count($result[$numitens-1], '}');
			if ($numitens < -10) {
				die("Erro de chaves. A página-fonte possui diferenças entre a quantidade de chaves \"}{\" em alguma linha.");
			}
		}
		
		//Separa dados numéricos e insere na array de resultado
		$resultado[$array1[1][0]][1] = trim($result[$numitens-4]);
		$resultado[$array1[1][0]][2] = trim($result[$numitens-3]);
		$resultado[$array1[1][0]][3] = str_replace('data-sort-value="-1" |{{Color|grey|No data}}', '{{color|darkgray|–}}', trim($result[$numitens-2]));

		//Processa a fonte e insere na array de resultado
		$resultado[$array1[1][0]][4] = str_replace($de, $para, preg_replace('/date=([0-9]{4})-([0-9]{2})-([0-9]{2})/', 'date=$3-$2-$1', trim($result[$numitens-1])));

		//Seção para ser utilizada em debug
		//var_dump($resultado[$array1[1][0]]);

		//Aviso de fim de loop
		echo "OK\n";
	}
}