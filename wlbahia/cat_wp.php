<?php

//Aumenta tempo limite de execução do script
set_time_limit(60);

//Define a variável de lista como uma array
$list = array();

//Verifica se execução é continuação de uma execução anterior. Caso sim, prossegue para próximo município
if (isset($_GET['continue'])) {
	$continue = "&cmcontinue=".$_GET['continue'];
} else {
	$continue = "";
}

//Recupera categoria do município na Wikipédia
$query = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmtitle=Categoria%3AMunic%C3%ADpios%20da%20Bahia&cmnamespace=14&cmlimit=1".$continue), true);

//Recupera itens da categoria do estado e insere em uma array
$category_state = $query['query']['categorymembers'];

//Processa categoria da cidade
//Teoricamente, esse loop só rodará uma única vez
foreach ($category_state as $city) {

	//Pula categorias que não são cidades
	if (in_array($city['pageid'], array(
		'3677117', //Categoria:Listas de municípios da Bahia
		'3877506'  //Categoria:!Predefinições sobre municípios da Bahia
	))) continue;
	
	//Abre categoria da cidade
	$category_city = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($city['title'])), true);

	//Insere cada item da categoria da cidade (arquivo ou subcategoria) em uma array
	foreach ($category_city['query']['categorymembers'] as $city_item) $city_list[] = $city_item;

	//Coleta outras páginas da categoria, caso existam
	while (isset($category_city['continue'])) {
		$continue_cat = $category_city['continue']['cmcontinue'];
		$category_city = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($city['title'])."&cmcontinue=".urlencode($continue_cat)), true);
		foreach ($category_city['query']['categorymembers'] as $city_item) $city_list[] = $city_item;
	}

	//Remove "Categoria:" do título
	$city_name = substr($city["title"], 10);

	//Define a subvariável da cidade na lista como uma array
	$list[$city_name] = array();

	//Processa cada item da array da cidade
	foreach ( $city_list as &$item ) { 

		//Pula itens que não são desejáveis
		if (preg_match("/naturais de/i", $item['title'])) continue;

		//Procedimento caso item seja uma página
	    if ($item['ns'] == '0') {

	    	//Insere código do item na subvariável da cidade na lista
	    	$list[$city_name][] = $item['pageid'];

	    //Procedimento caso item seja uma subcategoria
		} elseif ($item['ns'] == '14') {

			//Abre subcategoria da cidade
			$city_list_subcat = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($item['title'])), true);

			//Insere cada item da subcategoria da cidade em uma array
			foreach ($city_list_subcat['query']['categorymembers'] as $city_subitem) $city_list[] = $city_subitem;

			//Coleta outras páginas da subcategoria, caso exista
			while (isset($city_list_subcat['continue'])) {
				$continue_subcat = $city_list_subcat['continue']['cmcontinue'];
				$city_list_subcat = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($item['title'])."&cmcontinue=".urlencode($continue_subcat)), true);
				foreach ($city_list_subcat['query']['categorymembers'] as $city_subitem) $city_list[] = $city_subitem;
			}
		}
	}

	//Conta quantidade total de páginas únicas
	$list[$city_name] = count(array_unique($list[$city_name]));

	//Reseta lista da cidade
	unset($city_list);
}

//Coleta credenciais
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$database = "s54322__wlbahia";

//Conecta ao banco de dados
$con = mysqli_connect('tools.db.svc.eqiad.wmflabs', $ts_mycnf['user'], $ts_mycnf['password'], $database);
if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
	exit();
}

//Insere resultado no banco de dados
foreach ($list as $key => $value) {
	mysqli_query($con, "INSERT IGNORE INTO `list_wp` (`cidade`, `qnde`) VALUES ('".addslashes($key)."', '".$value."');");
}

//Retorna resultado do município
print_r($list);

//Imprime código para busca da próxima cidade
if (isset($query['continue']['cmcontinue'])) {
	echo('<script>window.location.href = \'cat_wp.php?continue='.urlencode($query['continue']['cmcontinue']).'\'</script>');

//Caso não existam mais cidades na lista, recupera dados dos artigos e procede para a atualização da página via bot
} else {

	//Recupera tabela completa no banco de dados
	$fetch = mysqli_query($con, "SELECT `cidade`, `qnde` FROM `list_wp` ORDER BY `cidade` ASC;");
	if (mysqli_num_rows($fetch) == 0) die("No data");

	//Define nome da cidade como chave da array
	foreach (mysqli_fetch_all($fetch) as $sql_data) {
		$list_sql[$sql_data['0']] = $sql_data['1'];
	}

	//Recupera nome das cidades na lista de artigos
	$raw = file_get_contents("https://pt.wikipedia.org/wiki/Lista_de_munic%C3%ADpios_da_Bahia?action=raw&section=1");
	preg_match_all('/\[\[(?!Municípios)([^:|\]]*)[|\]]/', $raw, $list_article);

	//Divide a lista em grupos de 47 (o limite da API é 50)
	$list_article = array_chunk($list_article['1'], 47);

	//Processa cada grupo de municípios e chama a API
	foreach ($list_article as $set) {
		$set = implode("|", $set);
		$url = "https://pt.wikipedia.org/w/api.php?action=query&format=json&prop=revisions&rvprop=content&rvslots=main&titles=".urlencode($set);
		$content = json_decode(file_get_contents($url), true)['query']['pages'];

		//Processa cada município e insere dados recuperados da API em uma array
		foreach ($content as $article) {
			$content = $article['revisions']['0']['slots']['main']['*'];
			preg_match_all('/<ref/', $content, $ref_call);
			$result[] = array($article['title'], strlen($content), count($ref_call['0']));
		}
	}

	//Monta wikicódigo
	$wikiCode = "{| class=\"wikitable sortable\"\n|-\n! Cidade\n! Bytes\n! Ref (Chamadas)\n! Subartigos\n";
	foreach ($result as $city) {

		//Utiliza nome do município como chave para array de categorias e subtrai 1 (artigo do município)
		//Caso não encontrado, a categoria não existe e o valor é zero
		if (isset($list_sql[$city["0"]])) {
			$cat = $list_sql[$city["0"]] - 1;
		} else {
			$cat = 0;
		}
		$wikiCode = $wikiCode."|-\n| [[".$city["0"]."]]\n| {{formatnum:".$city["1"]."}}\n| {{formatnum:".$city["2"]."}}\n| {{formatnum:".$cat."}}\n";
	}
	$wikiCode = $wikiCode."|}\n";

	//Login
	require __DIR__.'/../vendor/autoload.php';
	require __DIR__.'/../../credenciais.php';
	$wiki = new Wikimate('https://pt.wikipedia.org/w/api.php');
	if ($wiki->login($username, $password))
		echo '<br>Login OK<br>' ;
	else {
		$error = $wiki->getError();
		echo "<b>Wikimate error</b>: ".$error['login'];
	}

	//Recupera dados da página
	$page = $wiki->getPage('Wikipédia:Wiki Loves Bahia/Queries/Artigos dos municípios');
	if (!$page->exists()) die('Page not found');

	//Grava código
	if ($page->setText($wikiCode, 0, true, "bot: Atualizando query")) {
		echo "\nEdição realizada.\n";
	} else {
		$error = $page->getError();
		echo "\nError: " . print_r($error, true) . "\n";
	}

	//Reseta tabela para próxima execução
	mysqli_query($con, "TRUNCATE `list_wp`;");
}