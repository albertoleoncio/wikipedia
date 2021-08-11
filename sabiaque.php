<?php
include './bin/globals.php';

//Define fuso horário como UTC
date_default_timezone_set('UTC');

//Define data atual
$today = strtotime('today');

//Define $dados como uma array
$dados = array();

//Login
include './bin/api.php';
loginAPI($usernameSQ, $passwordSQ);

////////////////////////////////////////////////////////////////////////////////////////
//
//	LISTA DE VARIÁVEIS EM $dados e OUTRAS VARIÁVEIS
//
//	 $dados: array armazenadora das informações
//		[1]: texto da proposição
//		[2]: título do artigo-chave da proposição
//		[3]: nome de usuário do proponente
//		[4]: texto da proposição mais antiga para arquivamento
//		[5]: discussão da proposição para arquivamento
//
//	      A: página de propostas aprovadas
//	      B: predefinição da página principal
//	      C: página de discussão do artigo-chave
//	      D: página de discussão do usuário
//	      E: proposições recentes
//	      F: arquivo de discussão da proposição
//		  G: envio da proposição ao Facebook e ao Twitter
//
////////////////////////////////////////////////////////////////////////////////////////



////////////////////////////////////////////////////////////////////////////////////////
//
//	Contador
//
////////////////////////////////////////////////////////////////////////////////////////

//Recupera página do contador
if (!get_headers("https://pt.wikipedia.org/w/api.php?action=purge&format=none&forcerecursivelinkupdate=1&titles=Wikip%C3%A9dia%3ASabia%20que%2FFrequ%C3%AAncia")) die ("Erro durante purge.");
$htmlTime = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=expandtemplates&format=json&prop=wikitext&text=%7B%7BWikip%C3%A9dia%3ASabia_que%2FFrequ%C3%AAncia%7D%7D"), true)["expandtemplates"]["wikitext"];
if ($htmlTime === FALSE) die("Nao foi possível recuperar os dados do contador.");

//Limite de segurança
if (is_numeric($htmlTime) === FALSE OR $htmlTime < 43200) {
	die("'Wikipédia:Sabia que/Frequência' possui valor não numérico ou menor que 43200. Bloqueio de segurança.");
}

//Recupera horário da última alteração
$get = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=usercontribs&uclimit=1&ucuser=SabiaQueBot&ucprop=timestamp");
if ($get === FALSE) die("Nao foi possível recuperar os dados da API.");
$antes = date("U",strtotime(json_decode($get, true)['query']['usercontribs']['0']['timestamp']));

//Calcula diferença
$dif = ($antes + $htmlTime) - time();

//Continua atualização, ou retorna contagem regressiva e encerra o script
if ($dif < 0) {
	echo "Disponível para atualização.\n";
} else {
	echo "Contagem regressiva para atualização: ";
	if ($dif > ($htmlTime / 2)) {
		echo gmdate("j \d\i\a\, H:i:s", $dif - ($htmlTime / 2));
	} else {
		echo gmdate("H:i:s", $dif);
	}
	echo "\n";
	die();
}



////////////////////////////////////////////////////////////////////////////////////////
//
//	A-1
//
////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageA = "Wikipédia:Sabia que/Propostas/Aprovadas";
$htmlA = getAPI($pageA);

//Explode código, dividindo por tópicos
$htmlA_exploded = explode("\n==", $htmlA);

//Recupera número de seções e encerra script caso só exista uma ou nenhuma proposição
if (count($htmlA_exploded) <= 2) die("Não existem propostas para publicação.");

//Coleta proposição
preg_match_all('/\|texto = ([^\n]*)/', $htmlA_exploded[1], $output1);
$dados[1] = ltrim($output1[1][0],"… ");

//Coleta artigo-chave da proposição
preg_match_all('/\'\'\'\[\[([^\]\|\#]*)|\[\[([^\|]*)\|\'\'\'/', $output1[1][0], $output2);
$dados[2] = $output2[2][0].$output2[1][0];

//Coleta nome de proponente
preg_match_all('/\* \'\'\'Proponente\'\'\' – [^\[]*\[\[[^:]*:([^|]*)/', $htmlA_exploded[1], $output3);
$dados[3] = $output3[1][0];

//Coleta discussão da proposição e elimina proposição a publicar
$dados[5] = $htmlA_exploded[1];
unset($htmlA_exploded[1]);

//Remonta código da página
$htmlA = implode("\n==", $htmlA_exploded);



////////////////////////////////////////////////////////////////////////////////////////
//
//	B
//
////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageB = "Predefinição:Sabia que";

//Recupera codigo-fonte da página
$htmlB = getAPI($pageB);

//Explode código, dividindo por tópicos
$htmlBe = explode("\n…", $htmlB);

//Insere nova proposta com marcação de data, renumerando as demais
array_splice($htmlBe, 1, 0, " ".rtrim($dados[1])."<!--".strtolower(utf8_encode(strftime('%B de %Y', $today)))."-->\n");

//Explode último item da array, separando ultima proposição do rodapé da página
$ultima = explode("<!-- FIM", $htmlBe[count($htmlBe)-1]);

//Coleta texto da proposição para arquivamento
$dados[4] = ltrim($ultima[0]);

//Remonta rodapé da página
$htmlBe[count($htmlBe)-1] = "<!-- FIM".$ultima[1];

//Remonda último item da array
$htmlBe[count($htmlBe)-2] = $htmlBe[count($htmlBe)-2]."\n".$htmlBe[count($htmlBe)-1];

//Remove ultima proposição
array_pop($htmlBe);

//Remonta código da página
$htmlB = implode("\n…",$htmlBe);

//Grava página
editAPI($htmlB, NULL, FALSE, "bot: (1/6) Inserindo SabiaQue", $pageB, $usernameSQ);


////////////////////////////////////////////////////////////////////////////////////////
//
//	C
//
////////////////////////////////////////////////////////////////////////////////////////

//Verifica se página é redirect
$APIQuery = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&titles=".urlencode($dados[2])."&redirects=1"), TRUE);
if (isset($APIQuery["query"]["redirects"])) {
	$dados[2] = $APIQuery["query"]["redirects"][0]["to"];
}

//Define página
$pageC = "Discussão:".$dados[2];

//Recupera dados da seção inicial da página de discussão do artigo-chave
$htmlC = getsectionsAPI($pageC)['0'];

//Verifica se a predefinição já existe. Se sim, insere nova predefinição no final da seção. Se não...
if (strpos($htmlC, "SabiaQueDiscussão") == false) {
	$htmlC = $htmlC."\n\n{{SabiaQueDiscussão\n|data1    = ".utf8_encode(strftime('%d de %B de %Y', $today))."\n|entrada1 = … ".$dados[1]."\n}}";
} else {

	//A partir do número máximo (10), verifica qual o maior número encontrado.
	$n = 10;
	while ($n > 0 AND strpos($htmlC, "entrada".$n) == FALSE) {$n--;}

	//Caso n = 0, significa que a entrada mais recente não possui número (nesse caso, a proxima entrada é 2). Nos outros casos, a próxima entrada é a encontrada +1.
	if ($n == 0) {$n = 2;} else {$n++;}

	//Efetua inserção
	$htmlC = str_replace("{{SabiaQueDiscussão", "{{SabiaQueDiscussão\n|data".$n."    = ".utf8_encode(strftime('%d de %B de %Y', $today))."\n|entrada".$n." = … ".$dados[1], $htmlC);
}

//Grava página
editAPI($htmlC, 0, FALSE, "bot: (2/6) Inserindo SabiaQueDiscussão", $pageC, $usernameSQ);

////////////////////////////////////////////////////////////////////////////////////////
//
//	E
//
////////////////////////////////////////////////////////////////////////////////////////

//Define página e recupera seções da página
$pageE = "Wikipédia:Sabia que/Arquivo/Recentes";
$htmlE = getsectionsAPI($pageE);

//Cria mapa das seções, para que o nome do mês indique qual seção seja editada
$htmlE_map = array();
for ($i=0; $i < count($htmlE); $i++) { 
	preg_match_all('/=* ?([^= ]*?) =*\n/', $htmlE[$i], $month_name);
	if (isset($month_name['1']['0'])) $htmlE_map[$month_name['1']['0']] = $i;
}

//Explode proposição para arquivar, separando-o da data de duplicação
$recente = explode("<!--", $dados[4]);

//Isola o nome do mês de publicação
$recente[1] = ucfirst(explode(' ',trim($recente[1]))[0]);

//Verifica se a seção com o nome do mês já existe. A partir disso, monta código da seção
if (array_key_exists($recente[1], $htmlE_map)) {
	$htmlE[1] = preg_replace('/==\n/', "==\n*… ".$recente[0]."\n", $htmlE[1]);
	$section = 1;
} else {
	$htmlE[0] = $htmlE[0]."==== ".$recente[1]." ====\n*… ".$recente[0]."\n";
	$section = 0;
}

//Grava página
editAPI($htmlE[$section], $section, FALSE, "bot: (3/6) Inserindo Arquivo/Recentes", $pageE, $usernameSQ);



////////////////////////////////////////////////////////////////////////////////////////
//
//	F
//
////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageF = "Wikipédia:Sabia que/Propostas/Arquivo/".utf8_encode(strftime('%Y/%m', $today));

//Recupera codigo-fonte da página
$htmlF = getAPI($pageF);

//Monta código do arquivo
$htmlF = $htmlF."\n\n==".$dados[5]."{{ADC|sim|".utf8_encode(strftime('%d de %B de %Y', $today))."|~~~}}";

//Grava página
editAPI($htmlF, NULL, FALSE, "bot: (4/6) Inserindo Propostas/Arquivo", $pageF, $usernameSQ);
	


////////////////////////////////////////////////////////////////////////////////////////
//
//	A-2
//
////////////////////////////////////////////////////////////////////////////////////////

//Grava página
editAPI($htmlA, NULL, FALSE, "bot: (5/6) Arquivando proposição publicada", $pageA, $usernameSQ);


////////////////////////////////////////////////////////////////////////////////////////
//
//	D
//
////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageD = "Usuário Discussão:".$dados[3];

//Recupera codigo-fonte da página
$htmlD = getAPI($pageD);

//Monta código da ParabénsSQ
$htmlD = $htmlD."{{subst:ParabénsSQ|artigo=''[[".$dados[2]."]]''|data=".utf8_encode(strftime('%d de %B de %Y', $today))."|curiosidade=…".$dados[1]."|arquivo=".utf8_encode(strftime('%Y/%m', $today))."}} --~~~~";

//Grava página
editAPI($htmlD, NULL, FALSE, "bot: (6/6) Inserindo ParabénsSQ", $pageD, $usernameSQ);

////////////////////////////////////////////////////////////////////////////////////////
//
//	G
//
////////////////////////////////////////////////////////////////////////////////////////

//Monta status para envio ao Twitter
$twitter_status = "Você sabia que...\n\n…".preg_replace('/[\[\]\']/', '', preg_replace('/\[\[[^\|\]]*\|([^\]]*)\]\]/', '$1', $dados[1]))."\n\nLeia mais na Wikipédia: https://pt.wikipedia.org/wiki/".rawurlencode($dados[2]);

//Envia Tweet
require "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
define('CONSUMER_KEY', $twitter_consumer_key);
define('CONSUMER_SECRET', $twitter_consumer_secret);
$twitter_conn = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $twitter_access_token, $twitter_access_token_secret);
$post_tweets = $twitter_conn->post("statuses/update", ["status" => $twitter_status]);

//Retorna resultado
/*print_r($post_tweets['created_at']);
print_r($post_tweets['id']);*/

//Monta array para envio ao Facebook
/*$fb['message'] = "Você sabia que...\n\n…".$dados[1]."\n\nLeia mais na Wikipédia: https://pt.wikipedia.org/wiki/".rawurlencode($dados[2]);
$fb['access_token'] = $fb_token;
$fb['link'] = "https://pt.wikipedia.org/wiki/".rawurlencode($dados[2]);
$fb['caption'] = "Sabia que...";

//Executa cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/460984407268496/feed');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fb);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$return = curl_exec($ch);
curl_close($ch);

//Retorna resutado
print_r($return);*/


////////////////////////////////////////////////////////////////////////////////////////
//
//	Seção provisória, até a ativação da função de gravar página
//
////////////////////////////////////////////////////////////////////////////////////////

//print_r($dados);
/*echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Wikip%C3%A9dia:Sabia_que/Propostas/Aprovadas&action=edit">LINK</a>
	<textarea rows="4" cols="50">'.htmlentities($htmlA).'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:Sabia_que&action=edit">LINK</a>
	<textarea rows="4" cols="50">'.htmlentities($htmlB).'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/wiki/Discuss%C3%A3o:'.$dados[2].'?action=edit&section=0">LINK</a>
	<textarea rows="4" cols="50">'.htmlentities($htmlC).'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/wiki/Usu%C3%A1rio_Discuss%C3%A3o:'.$dados[3].'?action=edit&section=new">LINK</a>
	<textarea rows="4" cols="50">'.htmlentities($htmlD).'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Wikipédia:Sabia que/Arquivo/Recentes&action=edit&section='.$section.'">LINK</a>
	<textarea rows="4" cols="50">'.htmlentities($htmlE[$section]).'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Wikipédia:Sabia que/Propostas/Arquivo/'.utf8_encode(strftime('%Y/%m', $today)).'&action=edit&section=new">LINK</a>
	<textarea rows="4" cols="50">'.htmlentities($htmlF).'</textarea>';*/
?>

