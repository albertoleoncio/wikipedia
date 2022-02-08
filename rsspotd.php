<?php

header('Content-type: application/xml');

//Gera lista recente via API
$potd_api = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=revisions&titles=Usu%C3%A1rio(a)%3AAlbeROBOT%2FPOTD&rvprop=timestamp%7Ccontent%7Cids&rvslots=main&rvlimit=5");
$potd_api = unserialize($potd_api)["query"]["pages"]["6720820"]["revisions"];

//Processa cada item
foreach ($potd_api as $image) {

	//Busca página da imagem
	$text = file_get_contents("https://pt.wikipedia.org/w/api.php?action=parse&format=php&page=Wikip%C3%A9dia%3AImagem_em_destaque%2F".rawurlencode($image["slots"]["main"]["*"]));
	$text = unserialize($text)["parse"]["text"]["*"];

	//Extrai texto da imagem
	preg_match_all('/(?<=<img alt=")[^"]*/', $text, $content);

	//Extrai endereço da imagem
	preg_match_all('/(?<=<a href="\/wiki\/Ficheiro:)[^"]*/', $text, $address);

	//Extrai dados da imagem
	$headers = get_headers('https://pt.wikipedia.org/wiki/Especial:Redirecionar/file/'.$address["0"]["0"].'?width=1000', true);

	//Busca metadados da imagem
	$meta = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=imageinfo&iiprop=extmetadata&titles=Ficheiro:".rawurlencode($address["0"]["0"]));
	$meta = unserialize($meta)["query"]["pages"]["-1"]["imageinfo"]["0"]["extmetadata"];

	//Monta resposta para envio ao Twitter
	$twitter_reply = "Autor: ".strip_tags($meta["Artist"]["value"])." (Licença: ".strip_tags($meta["LicenseShortName"]["value"])." - ".strip_tags($meta["LicenseUrl"]["value"]).")";

	$potd[] = array(
		"title" 		=> $image["slots"]["main"]["*"],
		"description" 	=> "Imagem do dia em ".$image["slots"]["main"]["*"].": ".$content["0"]["0"]."\nAutor: ".strip_tags($meta["Artist"]["value"])." (Licença: ".strip_tags($meta["LicenseShortName"]["value"])." - ".strip_tags($meta["LicenseUrl"]["value"]).")\nVeja mais informações no link:",
		"link" 			=> "https://pt.wikipedia.org/wiki/WP:Imagem_em_destaque/".rawurlencode($image["slots"]["main"]["*"]),
		"timestamp" 	=> date('D, d M Y H:i:s O',strtotime($image["timestamp"])),
		"guid"			=> $image["revid"],
		"image_url" 	=> $headers["location"]
	);
}

//print_r($potd);
?>
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <atom:link href="https://alberobot.toolforge.org/rsspotd.php" rel="self" type="application/rss+xml" />
  <title>WikiPT - POTD</title>
  <link>https://pt.wikipedia.org/</link>
  <description>Wikipédia em português</description><?php

  foreach ($potd as $potd_item) {
	echo("\n  <item>");
	echo("\n    <title>".$potd_item["title"]."</title>");
	echo("\n    <link>".$potd_item["link"]."</link>");
	echo("\n    <pubDate>".$potd_item["timestamp"]."</pubDate>");
	echo("\n    <guid>https://pt.wikipedia.org/w/index.php?diff=".$potd_item["guid"]."</guid>");
	echo("\n    <description>".$potd_item["description"]."</description>");
	echo("\n    <enclosure url=\"".$potd_item["image_url"]."\" />");
	echo("\n  </item>");
  }

  ?>
</channel>

</rss>


