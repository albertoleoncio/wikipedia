<?php

header('Content-type: application/xml');

/////////
//
// EAD
//
/////////

//Gera lista recente via API
$ead_api = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=revisions&titles=Usu%C3%A1rio(a)%3AAlbeROBOT%2FEAD&rvprop=timestamp%7Ccontent%7Cids&rvslots=main&rvlimit=5");
$ead_api = unserialize($ead_api)["query"]["pages"]["6375156"]["revisions"];

//Processa cada item
foreach ($ead_api as $article) {
	$ead[] = array(
		"title" 		=> $article["slots"]["main"]["*"],
		"description"	=> $article["slots"]["main"]["*"]." é um artigo de destaque na Wikipédia!\n\nIsso significa que ele foi identificado como um dos melhores artigos produzidos pela comunidade da Wikipédia.\n\nO que achou? Ainda tem como melhorar?",
		"link" 			=> "https://pt.wikipedia.org/wiki/".rawurlencode($article["slots"]["main"]["*"]), 
		"timestamp" 	=> date('D, d M Y H:i:s O',strtotime($article["timestamp"])),
		"guid"			=> $article["revid"]
	);
}


/////////
//
// SABIA QUE
//
/////////

$sq_api = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&prop=revisions&titles=Usu%C3%A1rio(a)%3ASabiaQueBot%2Flog&rvprop=timestamp%7Ccontent%7Cids&rvslots=main&rvlimit=5");
$sq_api = unserialize($sq_api)["query"]["pages"]["6731563"]["revisions"];
foreach ($sq_api as $prop) {
	preg_match_all('/…[^\n]*/', $prop["slots"]["main"]["*"], $content);
	preg_match_all('/https:.*/', $prop["slots"]["main"]["*"], $address);
	preg_match_all('/(?<=wiki\/).*/', $prop["slots"]["main"]["*"], $title);
	$sq[] = array(
		"title"			=> $title["0"]["0"],
		"description" 	=> "Você sabia que...\n\n".$content["0"]["0"],
		"link" 			=> $address["0"]["0"],
		"timestamp"		=> date('D, d M Y H:i:s O',strtotime($prop["timestamp"])),
		"guid"			=> $prop["revid"]
	);
}

//print_r($ead);
//print_r($sq);
?>
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <atom:link href="https://alberobot.toolforge.org/rss.php" rel="self" type="application/rss+xml" />
  <title>WikiPT</title>
  <link>https://pt.wikipedia.org/</link>
  <description>Wikipédia em português</description><?php

  foreach ($ead as $ead_item) {
	echo("\n  <item>");
	echo("\n    <title>".$ead_item["title"]."</title>");
	echo("\n    <link>".$ead_item["link"]."</link>");
	echo("\n    <pubDate>".$ead_item["timestamp"]."</pubDate>");
	echo("\n    <guid>https://pt.wikipedia.org/w/index.php?diff=".$ead_item["guid"]."</guid>");
	echo("\n    <description>".$ead_item["description"]."</description>");
	echo("\n  </item>");
  }

  foreach ($sq as $sq_item) {
	echo("\n  <item>");
	echo("\n    <title>".$sq_item["title"]."</title>");
	echo("\n    <link>".$sq_item["link"]."</link>");
	echo("\n    <pubDate>".$sq_item["timestamp"]."</pubDate>");
	echo("\n    <guid>https://pt.wikipedia.org/w/index.php?diff=".$sq_item["guid"]."</guid>");
	echo("\n    <description>".$sq_item["description"]."</description>");
	echo("\n  </item>");
  }
  ?>
</channel>

</rss>

