<?php
include 'globals.php';
echo "<pre>";

//Recupera dados da fonte
$url = "https://en.wikipedia.org/w/index.php?title=Template:COVID-19_pandemic_data&action=raw";
$html = @file_get_contents($url);
echo "<b>Processamento da Template:COVID-19_pandemic_data</b>\n";

//Separa paises e insere em um array
$htmle = explode("\n|-", $html);

//Predefine $resultado como uma array
$resultado = array();
$wikien = array();
$wikipt = array();

//Lista de substituição
$de = array(" March 2020"," April 2020"," May 2020","|url-status=live","April 2, 2020");
$para = array("-03-2020","-04-2020","-05-2020","","02-04-2020"); 

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

		//var_dump($resultado[$array1[1][0]]);
		echo "OK\n";
	}
}

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login($username, $password))
	echo "<hr><b>Predefinição:Dados_da_pandemia_de_COVID-19</b>\n" ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Recupera dados da predefinição
$page = $wiki->getPage('Predefinição:Dados da pandemia de COVID-19');
if (!$page->exists()) die('Page not found');
$wikiCode = $page->getText();

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

		array_push($wikipt, @$key);

		//Verifica se o valor da string corresponde a um país listado na array de resultado
		if (array_key_exists($key, $resultado)) {

			//Substitui os dados no item com as informações atualizadas
			$pieces[$x] = 
				"#(".preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][0]).")-->{{formatnum:".
				preg_replace('/,/', '', preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][1]))."}} || {{formatnum:".
				preg_replace('/,/', '', preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][2]))."}} || {{formatnum:".
				preg_replace('/,/', '', preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][3]))."}} || ".
				preg_replace('/<!--[\s\S]*?-->/', '', $resultado[$key][4])."<!-- "
			;
		}
	}	
}

//Remonta o texto da predefinição a partir da array
$wikiCode = implode("#bot", $pieces);

//Gravar código
if ($page->setText($wikiCode, 0, true, "bot: Atualizando estatísticas")) {
	echo "<hr>Edição realizada.\n";
} else {
	$error = $page->getError();
	echo "<hr>Error: " . print_r($error, true) . "\n";
}

echo "<hr>";
$adicionar = array_diff($wikien, $wikipt);
if (($keyadd = array_search("Brazil", $adicionar)) !== false) {
    unset($adicionar[$keyadd]);
}
$eliminar = array_diff($wikipt, $wikien);
echo "Territórios para adicionar:\n";
print_r($adicionar);
echo "Territórios para remover:\n";
print_r($eliminar);

?>