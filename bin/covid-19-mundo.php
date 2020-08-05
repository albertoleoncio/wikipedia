<?php

//Evita que script seja carregado diretamente
if (!isset($isocode)) {
	die("Não deve ser chamado diretamente.");
}

function cleandata ($info) {
	$info = str_replace('data-sort-value="-1" |{{Color|grey|No data}}', '{{color|darkgray|–}}', trim($info));
	$info = str_replace('#invoke:WikidataIB|getValue', 'wdib', $info);
	$info = preg_replace('/<!--[\s\S]*?-->/', '', $info);
	$info = preg_replace('/,/', '', $info);
	return $info;
} 

//Recupera dados da fonte
$url = "https://en.wikipedia.org/w/index.php?title=Template:COVID-19_pandemic_data&action=raw";
$html = @file_get_contents($url);
echo "<b>Template:COVID-19 pandemic data</b>\n";

//Separa paises e insere em um array
$htmle = explode("\n|-", $html);

//Predefine $resultado e $wikien como uma array
$resultado = array();
$wikien = array();
$wikiXX = array();

//Loop para processar cada item da array
for ($x = 0; $x < count($htmle); $x++) {

	//Verifica se item possui marcação do arquivo de bandeira ou barco, indicando que a string se refere a um país ou a um cruzeiro
	if ((strpos($htmle[$x], '[[File:Flag') !== false) OR (strpos($htmle[$x], '[[File:Cruise') !== false) OR (strpos($htmle[$x], '[[File:Sub') !== false) OR (strpos($htmle[$x], '[[File:Flug') !== false)) {

		//Separa a string em substrings, baseado na marcação de estilo da tabela
		$result = preg_split('/\n *\|/', $htmle[$x]);

		//Separa o nome do país, elimina predefinições como as marcas de rodapé e insere na array de resultado como uma key
		preg_match_all(
			'/! ?scope="row" ?(?:data-sort-value="[^"]*" ?)?\| ?\'{0,2}\[\[[^F][^\|]*\|([^\|]*)]]/', 
			preg_replace('/{{[^}]*}}|<[^>]*>|\'|\([^\)]*\)/', '', $result[0]), 
			$array1
		);
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
		$resultado[$array1[1][0]][1] = cleandata($result[$numitens-4]);
		$resultado[$array1[1][0]][2] = cleandata($result[$numitens-3]);
		$resultado[$array1[1][0]][3] = cleandata($result[$numitens-2]);

		//Remove duplo formatnum
		for ($i=1; $i < 4; $i++) { 
			if(strpos($resultado[$array1[1][0]][$i], "formatnum") !== false){
			    $resultado[$array1[1][0]][$i] = preg_replace('/{{formatnum:(.*)}}/', '$1', $resultado[$array1[1][0]][$i]);
			} 
		}

		//Processa a fonte e insere na array de resultado
		$resultado[$array1[1][0]][4] = refparser(str_replace("\n", "", $result[$numitens-1]));

		//Seção para ser utilizada em debug
		//var_dump($resultado[$array1[1][0]]);

		//Aviso de fim de loop
		echo "OK\n";
	}
}

//Proteção contra bagunça na tabela
if (!$resultado['Brazil'][1] OR !$resultado['Brazil'][2] OR !$resultado['Brazil'][3]) die('Tabela com erro');

//Insere dados totais do mundo
$urltotal = "https://en.wikipedia.org/w/index.php?title=Template:Cases_in_the_COVID-19_pandemic&action=raw";
preg_match_all('/ \|[cdr][^=]*= ([0-9]*) *\n|({{cite[^}]*}})/', @file_get_contents($urltotal), $total);
array_push($wikien, 'World');
$resultado['World'][0] = "World";
$resultado['World'][1] = $total[1][0];
$resultado['World'][2] = $total[1][1];
$resultado['World'][3] = $total[1][2];
$resultado['World'][4] = "<ref>".$total[2][3]."</ref>";

//Login
$wiki = new Wikimate("https://".$isocode.".wikipedia.org/w/api.php");
if ($wiki->login('AlbeROBOT', $password))
	echo "<hr><b>".$template."</b>\n" ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Recupera dados da predefinição
$page = $wiki->getPage($template);
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getSection(0);

//Separa países e insere em uma array, utilizando a marcação do bot <!-- #bot#(País)-->
$pieces = explode("#bot", $wikiCode);

//Loop para processar cada item da array
for ($x = 0; $x < count($pieces); $x++) {

	//Verifica se item possui "#(", indicando que a string se refere a um país
	if (substr($pieces[$x], 0, 2) == "#(") {

		//Extrai o nome do país
		preg_match('/#\(([A-Za-z\ \.\-\&\(Åçãéí\']*)\){1,2}/', $pieces[$x], $keyarray);

		//Converte a array em uma string
		$key = $keyarray[1];
		echo @$key."\n";

		//Insere nome do país na lista do relatório
		array_push($wikiXX, @$key);

		//Verifica se o valor da string corresponde a um país listado na array de resultado
		if (array_key_exists($key, $resultado)) {

			//Substitui os dados no item com as informações atualizadas
			$parte = array();
			if (!isset($ignoreconf)) 	array_push($parte, "{{formatnum:".$resultado[$key][1]."}}");
			if (!isset($ignoremortes))	array_push($parte, "{{formatnum:".$resultado[$key][2]."}}");
			if (!isset($ignorecurados)) array_push($parte, "{{formatnum:".$resultado[$key][3]."}}");
			if (!isset($ignoreref)) 	array_push($parte, preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][4]));

			if ($resultado[$key][0] == "World" AND !isset($ignoretitle)) {$sep = "\n!";} else {$sep = "\n|";}
			$pieces[$x] = "#(".preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][0]).")-->".implode($sep, $parte)."<!-- ";
			
			unset($parte);
		}
	}	
}

//Remonta o texto da predefinição a partir da array
$wikiCode = implode("#bot", $pieces);

//Gravar código
if ($page->setText($wikiCode, 0, true, $sumario." ([[User:AlbeROBOT/".$log."|".$log."]])")) {
	echo "<hr>Edição realizada.\n";
} else {
	$error = $page->getError();
	echo "<hr>Error: " . print_r($error, true) . "\n";
}

//Gera relatório
$adicionar = array_diff($wikien, $wikiXX);
asort($adicionar);
$eliminar = array_diff($wikiXX, $wikien);
asort($eliminar);
$report = $toadd.":\n#".implode("\n#", $adicionar)."\n\n".$toremove.":\n#".implode("\n#", $eliminar);

//Grava log
$log = $wiki->getPage("User:AlbeROBOT/".$log);
if ($log->setText($report, 0, false, "")) {
	echo "<hr>Log gravado.\n";
} else {
	$error = $page->getError();
	echo "<hr>Error: " . print_r($error, true) . "\n";
}