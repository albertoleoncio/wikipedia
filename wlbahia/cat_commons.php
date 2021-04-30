<?php

//Aumenta tempo limite de execução do script
set_time_limit(120);

//Define a variável de lista como uma array
$list = array();

//Verifica se execução é continuação de uma execução anterior. Caso sim, prossegue para próximo município
if (isset($_GET['continue'])) {
	$continue = "&cmcontinue=".$_GET['continue'];
} else {
	$continue = "";
}

//Recupera categoria do município no commons
$query = json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=json&list=categorymembers&cmtitle=Category%3AMunicipalities%20in%20Bahia&cmnamespace=14&cmlimit=1".$continue), true);

//Recupera itens da categoria do estado e insere em uma array
$category_state = $query['query']['categorymembers'];

//Processa categoria da cidade
//Teoricamente, esse loop só rodará uma única vez
foreach ($category_state as $city) {

	//Pula categorias que não são cidades + Salvador, que é grande demais
	if (in_array($city['pageid'], array(
		'11433923', //Category:Categories of Bahia by city
		'93310338', //Category:City subdivisions in Bahia
		'93309946', //Category:Districts of cities in Bahia
		'2425759',  //Category:Locator maps of municipalities of Bahia
		'6356458',  //Category:Photographs of flags of municipalities of Bahia		
		'15670860'  //Category:Salvador, Bahia
	))) continue;
	
	//Abre categoria da cidade
	$category_city = json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($city['title'])), true);

	//Insere cada item da categoria da cidade (arquivo ou subcategoria) em uma array
	foreach ($category_city['query']['categorymembers'] as $city_item) $city_list[] = $city_item;

	//Coleta outras páginas da categoria, caso existam
	while (isset($category_city['continue'])) {
		$continue_cat = $category_city['continue']['cmcontinue'];
		$category_city = json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($city['title'])."&cmcontinue=".urlencode($continue_cat)), true);
		foreach ($category_city['query']['categorymembers'] as $city_item) $city_list[] = $city_item;
	}

	//Remove "Category:" do título
	$city_name = substr($city["title"], 9);

	//Define a subvariável da cidade na lista como uma array
	$list[$city_name] = array();

	//Processa cada item da array da cidade
	foreach ( $city_list as &$item ) { 

		//Pula itens que não são desejáveis
		if (
			$item['pageid'] == '3604337'     //Category:Guerra de Canutos
			OR preg_match("/bandeira|flag|bras[ãa]o|arms|\.svg/i", $item['title']) //Bandeiras, mapas e brasões
		) continue; 

		//Procedimento caso item seja um arquivo
	    if ($item['ns'] == '6') {

	    	//Insere código do item na subvariável da cidade na lista
	    	$list[$city_name][] = $item['pageid'];

	    	//Insere nome de usuário que enviou o arquivo em outra array
	    	$creators[$city_name][] = end(json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=revisions&list=&rvprop=user&rvlimit=1&rvdir=newer&pageids=".urlencode($item['pageid'])), true)["query"]["pages"])["revisions"]['0']["user"];

	    //Procedimento caso item seja uma subcategoria, ignorando categorias de pessoas
		} elseif (
			$item['ns'] == '14' AND (
				substr($item['title'], 0, 15) != "Category:People" 
				OR substr($item['title'], 0, 15) != "Category:Births"
			)
		) {
			//Abre subcategoria da cidade
			$city_list_subcat = json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($item['title'])), true);

			//Insere cada item da subcategoria da cidade em uma array
			foreach ($city_list_subcat['query']['categorymembers'] as $city_subitem) $city_list[] = $city_subitem;

			//Coleta outras páginas da subcategoria, caso existam
			while (isset($city_list_subcat['continue'])) {
				$continue_subcat = $city_list_subcat['continue']['cmcontinue'];
				$city_list_subcat = json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=json&list=categorymembers&cmlimit=500&cmtitle=".urlencode($item['title'])."&cmcontinue=".urlencode($continue_subcat)), true);
				foreach ($city_list_subcat['query']['categorymembers'] as $city_subitem) $city_list[] = $city_subitem;
			}
		}
	}

	//Conta quantidade total de nomes de usuários únicos
	@$creators[$city_name] = count(array_unique($creators[$city_name]));

	//Conta quantidade total de arquivos únicos
	$list[$city_name] = count(array_unique($list[$city_name]));

	//Reseta lista da cidade
	unset($city_list);
}

//Coleta credenciais do banco de dados
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
	mysqli_query($con, "INSERT IGNORE INTO `list` (`cidade`, `qnde`, `creators`) VALUES ('".addslashes($key)."', '".$value."', '".$creators[$key]."');");
}

//Imprime, na tela, a quantidade total de arquivos da cidade
print_r($list);

//Imprime código para busca da próxima cidade
if (isset($query['continue']['cmcontinue'])) {
	echo('<script>window.location.href = \'cat_commons.php?continue='.urlencode($query['continue']['cmcontinue']).'\'</script>');

//Caso não existam mais cidades na lista, procede para a atualização da página via bot
} else {

	//Recupera tabela no banco de dados
	$fetch = mysqli_query($con, "SELECT * FROM `list` ORDER BY `cidade` ASC;");
	if (mysqli_num_rows($fetch) == 0) die("No data");

	//Coleta quantidade de arquivos em Salvador via PetScan
	$ssa_count = count(json_decode(file_get_contents("https://petscan.wmflabs.org/?&since_rev0=&project=wikimedia&cb_labels_any_l=1&negcats=People%20of%20Salvador,%20Bahia&edits%5Bflagged%5D=both&categories=Salvador,%20Bahia&search_max_results=500&cb_labels_yes_l=1&edits%5Bbots%5D=both&cb_labels_no_l=1&depth=50&interface_language=en&language=commons&edits%5Banons%5D=both&ns%5B6%5D=1&minlinks=&format=json&doit="), true)["*"]['0']['a']["*"]);

	//Monta wikicódigo
	$wikiCode = "{| class=\"wikitable sortable\"\n|-\n! Cidade\n! Arquivos\n! Criadores\n|-\n| [[:c:Category:Salvador, Bahia|Salvador, Bahia]]\n| ".$ssa_count."\n| ?\n";
	while ($row = mysqli_fetch_assoc($fetch)) $wikiCode = $wikiCode."|-\n| [[:c:Category:".$row["cidade"]."|".$row["cidade"]."]]\n| ".$row["qnde"]."\n| ".$row["creators"]."\n";
	$wikiCode = $wikiCode."|}\n";

	//Login
	require __DIR__.'/../vendor/autoload.php';
	require __DIR__.'/../../credenciais.php';
	$wiki = new Wikimate('https://pt.wikipedia.org/w/api.php');
	if ($wiki->login('AlbeROBOT', $password))
		echo '<br>Login OK<br>' ;
	else {
		$error = $wiki->getError();
		echo "<b>Wikimate error</b>: ".$error['login'];
	}

	//Recupera dados da página
	$page = $wiki->getPage('Wikipédia:Wiki Loves Bahia/Queries/Commons');
	if (!$page->exists()) die('Page not found');

	//Grava código
	if ($page->setText($wikiCode, 0, true, "bot: Atualizando query")) {
		echo "\nEdição realizada.\n";
	} else {
		$error = $page->getError();
		echo "\nError: " . print_r($error, true) . "\n";
	}

	//Reseta tabela para próxima execução
	mysqli_query($con, "TRUNCATE `list`;");
}