<?php

////////////////////////////////////////////////////////////////////////////////////////
//
//	A: Seção pré-execução
//
////////////////////////////////////////////////////////////////////////////////////////

//Evita que script seja carregado diretamente
if (!isset($isocode)) die("Não deve ser chamado diretamente.");

//Inclui wikidata.php, caso a opção de cálculo de população esteja ativada
if (!isset($ignorepop))	include_once './bin/wikidata.php';

//Funções
function cleandata ($info) {
	$info = str_replace('data-sort-value="-1" |{{Color|grey|No data}}', '{{color|darkgray|–}}', trim($info));
	$info = str_replace('#invoke:WikidataIB|getValue', 'wdib', $info);
	$info = preg_replace('/<!--[\s\S]*?-->/', '', $info);
	$info = preg_replace('/,/', '', $info);
	return $info;
}
function rate ($território, $data1, $data2) {
	if ($território == "World") {
		return "{{color|darkgray|–}}";
	} else {
		return "{{#iferror:{{#expr: ( 100 * {{#invoke:String|replace|source=".$data2."|pattern=%D|replace=|plain=false}}) / {{#invoke:String|replace|source=".$data1."|pattern=%D|replace=|plain=false}} round 2}} %|{{color|darkgray|–}}}}";
	}
}
function pop ($território, $popresult, $data2) {
	if (array_key_exists($território, $popresult)) {
		$pop = $popresult[$território];
	} else {
		$pop = 0;
	}
	return "{{#iferror:{{#expr:({{#invoke:String|replace|source=".$data2."|pattern=%D|replace=|plain=false}} * 1000000) / ".$pop." round 0}}|{{color|darkgray|–}}}}";
}

////////////////////////////////////////////////////////////////////////////////////////
//
//	B: Insere informações da fonte na array $dados
//
////////////////////////////////////////////////////////////////////////////////////////

//Recupera dados da fonte
$url = "https://en.wikipedia.org/w/index.php?title=Template:COVID-19_pandemic_data&action=raw";
$html = @file_get_contents($url);
echo "<b>Template:COVID-19 pandemic data</b>\n";

//Separa linhas da tabela e insere em um array
$linhas = explode("\n|-", $html);

//Predefine arrays
$dados = array();
$wikien = array();
$wikiXX = array();

//Loop para processar cada item da array 
for ($x = 0; $x < count($linhas); $x++) {

	//Verifica se a linha possui marcação do ícone de bandeira ou navio, indicando que a string se refere a um território ou a um navio
	if ((strpos($linhas[$x], '[[File:Flag') !== false) 
		OR (strpos($linhas[$x], '[[File:Cruise') !== false) 
		OR (strpos($linhas[$x], '[[File:Sub') !== false) 
		OR (strpos($linhas[$x], '[[File:Flug') !== false)) {

		//Separa a linha em celulas, baseado na marcação de estilo da tabela
		$celula = preg_split('/\n *\|/', $linhas[$x]);

		//Separa o nome do território, elimina quinqilharias (ex: marcas de rodapé) e insere na array de resultado como uma key
		preg_match_all(
			'/! ?scope="row" ?(?:data-sort-value="[^"]*" ?)?\| ?\'{0,2}\[\[[^F][^\|]*\|([^\|]*)]]/', 
			preg_replace('/{{[^}]*}}|<[^>]*>|\'|\([^\)]*\)/', '', $celula[0]), 
			$array1
		);
		$território = @trim($array1[1][0]);
		echo $território."...";

		//Insere nome do território na lista do relatório
		array_push($wikien, $território);

		//Insere o nome do território como um valor na array de resultado
		$dados[$território][0] = $território;

		//Conta o numero de celulas dentro da array
		$numitens = count($celula);

		//Confere se existe uma diferença entre a quantidade de chaves "}{", o que indica que a fonte está dividida em duas strings
		$abrechave = substr_count($celula[$numitens-1], '{');
		$fechachave = substr_count($celula[$numitens-1], '}');
		while ($abrechave !== $fechachave) {
			//Concatena as duas ultimas strings, elimina a última e subtrai 1 na contagem de strings da array
			$celula[$numitens-2] = $celula[$numitens-2].'|'.$celula[$numitens-1];
			unset($celula[$numitens-1]);
			$numitens--;
			$abrechave = substr_count($celula[$numitens-1], '{');
			$fechachave = substr_count($celula[$numitens-1], '}');
			if ($numitens < -10) {
				die("Erro de chaves. A página-fonte possui diferenças entre a quantidade de chaves \"}{\" em alguma linha.");
			}
		}
		
		//Separa dados numéricos e insere na array de resultado
		$dados[$território][1] = cleandata($celula[$numitens-4]);
		$dados[$território][2] = cleandata($celula[$numitens-3]);
		$dados[$território][3] = cleandata($celula[$numitens-2]);

		//Remove duplo formatnum
		for ($i=1; $i < 4; $i++) { 
			if(strpos($dados[$território][$i], "formatnum") !== false){
			    $dados[$território][$i] = preg_replace('/{{formatnum:(.*)}}/', '$1', $dados[$território][$i]);
			} 
		}

		//Processa a fonte e insere na array de resultado
		$dados[$território][4] = str_replace("\n", "", $celula[$numitens-1]);

		//Seção para ser utilizada em debug
		//var_dump($dados[$território]);

		//Aviso de fim de loop
		echo "OK\n";
	}
}

//Proteção contra bagunça na tabela. Nesse caso, Brazil é utilizado como teste
if (!$dados['Brazil'][1] OR !$dados['Brazil'][2] OR !$dados['Brazil'][3]) die('Tabela com erro');

//Insere dados totais do mundo
$urltotal = "https://en.wikipedia.org/w/index.php?title=Template:Cases_in_the_COVID-19_pandemic&action=raw";
preg_match_all('/ \|[cdr][^=]*= ([0-9]*) *\n|({{cite[^}]*}})/', @file_get_contents($urltotal), $total);
array_push($wikien, 'World');
$dados['World'][0] = "World";
$dados['World'][1] = $total[1][0];
$dados['World'][2] = $total[1][1];
$dados['World'][3] = "{{color|darkgray|–}}";
$dados['World'][4] = "<ref>".$total[2][2]."</ref>";

//Limpa string para ser utilizada posteriormente
unset($território);

////////////////////////////////////////////////////////////////////////////////////////
//
//	C: Atualiza dados da wiki de destino
//
////////////////////////////////////////////////////////////////////////////////////////

//Login
$endPoint = "https://".$isocode.".wikipedia.org/w/api.php";
include './bin/api.php';
loginAPI($username, $password);
echo "<hr><b>".$template."</b>\n";

//Recupera dados da predefinição
$page = $template;
$wikiCode = getsectionsAPI($page)['0'];

//Separa territórios e insere em uma array, utilizando a marcação do bot <!-- #bot#(Território)-->
$seções = explode("#bot", $wikiCode);

//Loop para processar cada item da seção
for ($x = 0; $x < count($seções); $x++) {

	//Verifica se item possui "#(", indicando que a string se refere a um território
	if (substr($seções[$x], 0, 2) == "#(") {

		//Extrai o nome do território
		preg_match('/#\(([A-Za-z\ \.\,\-\&\(Åçãéí\']*)\){1,2}/', $seções[$x], $nome);

		//Converte a array em uma string
		$território = $nome[1];
		echo @$território."\n";

		//Insere nome do território na lista do relatório
		array_push($wikiXX, @$território);

		//Verifica se o valor da string corresponde a um território listado na array de dados
		if (array_key_exists($território, $dados)) {

			//Substitui os dados no item com as informações atualizadas
			$separador = "\n|";
			$parte = array();
			if (!isset($ignoreconf))    array_push($parte, "{{formatnum:".$dados[$território][1]."}}");
			if (!isset($ignoremortes))  array_push($parte, "{{formatnum:".$dados[$território][2]."}}");
			if (!isset($ignorecurados)) array_push($parte, "{{formatnum:".$dados[$território][3]."}}");
			if (!isset($ignorerate))    array_push($parte, "{{formatnum:".rate($território, $dados[$território][1], $dados[$território][2])."}}");
			if (!isset($ignorepop))     array_push($parte, "{{formatnum:".pop($território, $popresult, $dados[$território][2])."}}");
			if (!isset($ignoreref))     array_push($parte, refparser(preg_replace('/<!--[\s\S]*?-->/', '', preg_replace('/#invoke:cite ([^\|]*)\| ?\|/', 'cite $1|', $dados[$território][4]))));

			//Define separador distinto para título da tabela
			if (!isset($ignoretitle) AND $território == "World") $separador = "\n!";

			//Reune partes e refaz seção
			$seções[$x] = "#(".preg_replace('/<!--[\s\S]*?-->/', '', $dados[$território][0]).")-->".implode($separador, $parte)."<!-- ";
			
			//Limpa string para ser utilizada posteriormente
			unset($parte);
		}
	}
}

//Remonta o texto da predefinição a partir da array de seções
$wikiCode = implode("#bot", $seções);

//Gravar código
editAPI($wikiCode, 0, true, $sumario." ([[User:AlbeROBOT/".$log."|".$log."]])", $page, $username);

////////////////////////////////////////////////////////////////////////////////////////
//
//	D: Gera relatório da atualização
//
////////////////////////////////////////////////////////////////////////////////////////

//Monta relatório
$adicionar = array_diff($wikien, $wikiXX);
asort($adicionar);
$eliminar = array_diff($wikiXX, $wikien);
asort($eliminar);
$report = $toadd.":\n#".implode("\n#", $adicionar)."\n\n".$toremove.":\n#".implode("\n#", $eliminar);

//Grava relatório
editAPI($report, 0, false, "bot: ".$log, "User:AlbeROBOT/".$log, $username);