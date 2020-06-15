<?php
echo "<pre>";
include 'globals.php';
include 'covid-19-mundo.php';

//Predefine $wikipt como uma array
$wikipt = array();

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
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

		//Insere nome do país na lista do relatório
		array_push($wikipt, @$key);

		//Verifica se o valor da string corresponde a um país listado na array de resultado
		if (array_key_exists($key, $resultado)) {

			//Substitui os dados no item com as informações atualizadas, removendo comentários que estejam na tabela da wiki-en
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

//Gera relatório
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