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

//Busca imagem
$text = file_get_contents("https://pt.wikipedia.org/w/api.php?action=parse&format=php&page=Wikip%C3%A9dia%3AImagem_em_destaque%2F".rawurlencode($atual));
$text = unserialize($text)["parse"]["text"]["*"];
preg_match_all('/(?<=<a href="\/wiki\/Ficheiro:)[^"]*/', $text, $image);
preg_match_all('/(?<=\.)\w*?$/', $image["0"]["0"], $extension);
$path = './potd.'.$extension['0']['0'];
file_put_contents($path, file_get_contents('https://pt.wikipedia.org/wiki/Especial:Redirecionar/file/'.$image["0"]["0"].'?width=1000'));

//Monta status para envio ao Twitter
$twitter_status = "Imagem do dia em ".$atual.". Veja mais informações em https://pt.wikipedia.org/wiki/WP:Imagem_em_destaque/".rawurlencode($atual);
print_r($twitter_status);

//Envia Tweet
require "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
define('CONSUMER_KEY', $twitter_consumer_key);
define('CONSUMER_SECRET', $twitter_consumer_secret);
$twitter_conn = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $twitter_access_token, $twitter_access_token_secret);
$media = $twitter_conn->upload('media/upload', ['media' => $path]);
$parameters = [
    'status' => $twitter_status,
    'media_ids' => $media->media_id_string
];
$result = $twitter_conn->post('statuses/update', $parameters);
unlink($path);

//Retorna resultado
print_r($result->created_at);
print_r($result->id);
echo("\nOK!");

//Busca metadados da imagem
$meta = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=imageinfo&iiprop=extmetadata&titles=Ficheiro:".$image["0"]["0"]);
$meta = unserialize($meta)["query"]["pages"]["-1"]["imageinfo"]["0"]["extmetadata"];

//Monta resposta para envio ao Twitter
$twitter_reply = "Autor: ".strip_tags($meta["Artist"]["value"])." (Licença: ".strip_tags($meta["LicenseShortName"]["value"])." - ".strip_tags($meta["LicenseUrl"]["value"]).")";
print_r($twitter_reply);
$parameters_reply = [
    'status' => $twitter_reply,
    'in_reply_to_status_id' => $result->id
];
$result_reply = $twitter_conn->post('statuses/update', $parameters_reply);

//Retorna resultado
print_r($result_reply->created_at);
print_r($result_reply->id);
echo("\nOK!");