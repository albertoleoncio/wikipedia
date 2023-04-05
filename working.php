<pre><?php
require_once './bin/globals.php';
$api_url = 'https://pt.wikisource.org/w/api.php';

//Login
require_once './bin/api.php';
loginAPI($username, $password);

//Funções
function api_get($params) {
    global $username;
    $ch1 = curl_init( "https://pt.wikisource.org/w/api.php?" . http_build_query( $params ) );
    curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch1, CURLOPT_COOKIEJAR, $username."_cookie.inc" );
    curl_setopt( $ch1, CURLOPT_COOKIEFILE, $username."_cookie.inc" );
    $data = curl_exec( $ch1 );
    curl_close( $ch1 );
    return $data;
}

//Coleta edições nas mudanças recentes
$mr = [
    "action"        => "query",
    "format"        => "php",
    "list"          => "recentchanges",
    "formatversion" => "2",
    "rcnamespace"   => "106",
    "rcprop"        => "title",
    "rclimit"       => "max",
    "rctoponly"     => 1
];
$list = unserialize(api_get($mr))["query"]["recentchanges"];

//Isola o nome do arquivo
foreach ($list as $page) {
    $rootpage = explode("/", $page["title"]);
    if (!isset($rootpage['1'])) continue; //TODO: Imaginar um caminho alternativo para Indexes de imagens
    $indexes[] = "Index:".substr($rootpage['0'], 8);
}

//Elimina duplicatas
$indexes = array_unique($indexes);

//Isola os 10 indexes mais recentes
$indexes = array_slice($indexes, 0, 10);

//Organiza em ordém alfabética
asort($indexes);

//Coleta dados de qualidade das páginas de cada Index
foreach ($indexes as $index) {
    $pages_params = [
        "action"        => "query",
        "format"        => "php",
        "prop"          => "proofread",
        "generator"     => "proofreadpagesinindex",
        "formatversion" => "2",
        "gprppiititle"  => $index
    ];

    $pages = unserialize(api_get($pages_params))["query"]["pages"];

    $quality[$index]['x'] = 0;
    $quality[$index]['0'] = 0;
    $quality[$index]['1'] = 0;
    $quality[$index]['2'] = 0;
    $quality[$index]['3'] = 0;
    $quality[$index]['4'] = 0;

    foreach ($pages as $page) {
        if (isset($page["missing"])) {
            $quality[$index]['x']++;
        } elseif($page["proofread"]["quality"] == 0) {
            $quality[$index]['0']++;
        } elseif($page["proofread"]["quality"] == 1) {
            $quality[$index]['1']++;
        } elseif($page["proofread"]["quality"] == 2) {
            $quality[$index]['2']++;
        } elseif($page["proofread"]["quality"] == 3) {
            $quality[$index]['3']++;
        } elseif($page["proofread"]["quality"] == 4) {
            $quality[$index]['4']++;
        } else {
            die($page);
        }
    }
}

//Gera wikitexto
$wikitext = "<templatestyles src='Progressos recentes/styles.css' />\n{|\n";
foreach ($quality as $index => $item) {

    //Calcula porcentagens
    $total = array_sum($item);
    $item['0'] = round(($item['0'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
    $item['1'] = round(($item['1'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
    $item['2'] = round(($item['2'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
    $item['3'] = round(($item['3'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
    $item['4'] = round(($item['4'] / $total), 2, PHP_ROUND_HALF_DOWN) * 100;
    $item['x'] = 100 - $item['0'] - $item['1'] - $item['2'] - $item['3'] - $item['4'];

    //Coleta nome "oficial" do livro
    $content_params = [
        "action"        => "parse",
        "format"        => "php",
        "page"          => $index,
        "prop"          => "wikitext",
        "formatversion" => "2"
    ];
    $content = unserialize(api_get($content_params))["parse"]["wikitext"];
    preg_match('/Título=([\s\S]*?)\\n/', $content, $title);
    $title = trim($title['1'], '[]');
    $title_exploded = explode('|', $title);
    $title = end($title_exploded);

    $wikitext .= "|-\n| {{Barra de progresso|${item['1']}|${item['2']}|${item['3']}|${item['4']}|${item['0']}|${item['x']}}}\n| [[${index}|${title}]]\n";
}
$wikitext .= "|}<noinclude>{{documentação}}</noinclude>";

//Grava página
editAPI(
    $wikitext,
    null,
    false,
    "bot: Atualizando progressos",
    "Predefinição:Progressos recentes",
    $username
);