<pre><?php

//Recupera artigo atual
$atual = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=expandtemplates&format=json&prop=wikitext&text=".rawurlencode("{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}")), true)['expandtemplates']['wikitext'];
if ($atual === FALSE OR $atual == "") die("Nao foi possível recuperar os dados (1).");

//Recupera último artigo publicado
$ultimo = file_get_contents("https://pt.wikipedia.org/w/index.php?title=Usu%C3%A1rio(a):AlbeROBOT/POTD&action=raw");
if ($ultimo === FALSE OR $ultimo == "") die("Nao foi possível recuperar os dados (2).");

//Encerra script caso o último artigo publicado seja o artigo atual
if ($atual == $ultimo) die("Nada a alterar!");

//Login
include './bin/globals.php';
include './bin/api.php';
loginAPI($username, $password);

//Define página de usuário
$page = 'Usuário(a):AlbeROBOT/POTD';

//Gravar código
editAPI($atual, 0, true, "bot: Atualizando POTD", $page, $username);

//Busca endereço da imagem
$text = file_get_contents("https://pt.wikipedia.org/w/api.php?action=parse&format=php&page=Wikip%C3%A9dia%3AImagem_em_destaque%2F".rawurlencode($atual));
$text = unserialize($text)["parse"]["text"]["*"];
preg_match_all('/href="\/wiki\/Ficheiro:([^"]*)"/', $text, $image);
$image = $image["1"]["0"];

//Monta status para envio ao Twitter
$twitter_status = "Imagem do dia em ".$atual.". Veja mais em https://pt.wikipedia.org/wiki/WP:Imagem_em_destaque/".rawurlencode($atual)."\n\n\nhttps://pt.wikipedia.org/wiki/Image:".rawurlencode($image);
print_r($twitter_status);

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