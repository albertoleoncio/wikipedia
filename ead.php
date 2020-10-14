<pre><?php

//Recupera artigo atual
$atual = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=expandtemplates&format=json&text=%7B%7BEm%20destaque%2Flistagem%7C%7B%7BEm%20destaque%2Fcontador%7D%7D%7D%7D&prop=wikitext"), true)['expandtemplates']['wikitext'];
if ($atual === FALSE OR $atual == "") die("Nao foi possível recuperar os dados.");

//Recupera último artigo publicado
$ultimo = file_get_contents("https://pt.wikipedia.org/w/index.php?title=Usu%C3%A1rio(a):AlbeROBOT/EAD&action=raw");
if ($ultimo === FALSE OR $ultimo == "") die("Nao foi possível recuperar os dados.");

//Encerra script caso o último artigo publicado seja o artigo atual
if ($atual == $ultimo) die("Nada a alterar!");

//Login
include './bin/globals.php';
$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
	echo 'Login OK<br>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
}

//Recupera dados da página de usuário
$page = $wiki->getPage('Usuário(a):AlbeROBOT/EAD');
if (!$page->exists()) die('Page not found');

//Gravar código
if ($page->setText($atual, 0, true, "bot: Atualizando EAD")) {
	echo "\nEdição em página realizada.\n";
} else {
	$error = $page->getError();
	echo "\nError: " . print_r($error, true) . "\n";
}

//Monta status para envio ao Twitter
$twitter_status = $atual." é um artigo de destaque na Wikipédia!\n\nIsso significa que ele foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.\n\nO que achou? Ainda tem como melhorar?\nhttps://pt.wikipedia.org/wiki/".rawurlencode($atual);

//Envia Tweet
require "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
define('CONSUMER_KEY', $twitter_consumer_key);
define('CONSUMER_SECRET', $twitter_consumer_secret);
$twitter_conn = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $twitter_access_token, $twitter_access_token_secret);
$post_tweets = $twitter_conn->post("statuses/update", ["status" => $twitter_status]);

//Retorna resultado
print_r($post_tweets)['created_at'];
print_r($post_tweets)['id'];

//Monta array para envio ao Facebook
/*$fb['message'] = $atual." é um artigo de destaque na Wikipédia!\n\nIsso significa que ele foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.\n\nEste artigo figurará na Página principal da Wikipédia lusófona como Artigo destacado a partir de hoje.\n\nO que achou? Ainda tem como melhorar?\nhttps://pt.wikipedia.org/wiki/".rawurlencode($atual);
$fb['access_token'] = $fb_token;
$fb['link'] = "https://pt.wikipedia.org/wiki/".rawurlencode($atual);
$fb['caption'] = "Artigo destacado";

//Executa cURL do Facebook
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/460984407268496/feed');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fb);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$return = curl_exec($ch);
curl_close($ch);

//Retorna resutado
print_r($return);*/