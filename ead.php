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
require_once './bin/globals.php';
require_once './bin/api.php';
loginAPI($username, $password);

//Define página de usuário
$page = 'Usuário(a):AlbeROBOT/EAD';

//Gravar código
editAPI($atual, 0, true, "bot: Atualizando EAD", $page, $username);

//Monta status para envio ao Twitter
$twitter_status = $atual." é um artigo de destaque na Wikipédia!\n\nIsso significa que ele foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.\n\nO que achou? Ainda tem como melhorar?\nhttps://pt.wikipedia.org/wiki/".rawurlencode($atual);

//Envia Tweet
require_once "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
define('CONSUMER_KEY', $twitter_consumer_key);
define('CONSUMER_SECRET', $twitter_consumer_secret);
$twitter_conn = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $twitter_access_token, $twitter_access_token_secret);
$post_tweets = $twitter_conn->post("statuses/update", ["status" => $twitter_status]);

//Retorna resultado
print_r($post_tweets->created_at);
print_r($post_tweets->id);
echo("\nOK!");