<?php

$variations[] = array("Brasil", 	"brasileiro");
$variations[] = array("Portugal", 	"europeu‎");
$variations[] = array("Moçambique", "moçambicano");
$variations[] = array("Angola", 	"angolano");

foreach ($variations as $variation) {
	//Reseta variáveis
	unset($articles);
	unset($embedded);

	//Echo
	echo("\n\nExecutando categoria de artigos em português ".$variation["1"]."...");

	//Categoria da variante
	$api_cat = unserialize(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=categorymembers&cmtitle=Categoria%3A!Artigos%20escritos%20em%20portugu%C3%AAs%20".urlencode($variation["1"])."&cmprop=title&cmnamespace=1&cmlimit=500"));
	foreach ($api_cat["query"]["categorymembers"] as $page) $articles[] = $page["title"];
	while (isset($api_cat['continue'])) {
		$continue_api_cat = $api_cat['continue']['cmcontinue'];
		$api_cat = unserialize(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=categorymembers&cmtitle=Categoria%3A!Artigos%20escritos%20em%20portugu%C3%AAs%20".urlencode($variation["1"])."&cmprop=title&cmnamespace=1&cmlimit=500&cmcontinue=".urlencode($continue_api_cat)));
		foreach ($api_cat['query']['categorymembers'] as $page) $articles[] = $page["title"];
	}

	//Echo
	echo("<ul>".count($articles)." artigos listados na categoria e ");

	//Afluentes da variante
	$api_embedded = unserialize(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&einamespace=8&eilimit=500&list=embeddedin&eititle=MediaWiki%3AEditnotice-0-".urlencode($variation["0"])));
	foreach ($api_embedded["query"]["embeddedin"] as $page) $embedded[] = $page["title"];
	while (isset($api_embedded['continue'])) {
		$continue_api_embedded = $api_embedded['continue']['eicontinue'];
		$api_embedded = unserialize(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&einamespace=8&eilimit=500&list=embeddedin&eititle=MediaWiki%3AEditnotice-0-".urlencode($variation["0"])."&eicontinue=".urlencode($continue_api_embedded)));
		foreach ($api_embedded["query"]["embeddedin"] as $page) $embedded[] = $page["title"];
	}

	//Echo
	echo(count($embedded)." editnotices listados como afluentes da editnotice do país.</ul>");

	//Compara editnotices da variante
	foreach ($articles as $article) {
		if ($article == "Discussão:".$variation["0"]) continue;
		$article = substr_replace($article, "MediaWiki:Editnotice-0-", 0, 11);
		$article = str_replace("/", "-", $article);
		if (in_array($article, $embedded)) continue;
		echo ("<ul><b>ERRO: O editnotice <a href='https://pt.wikipedia.org/w/index.php?title=".urlencode($article)."'>".$article."</a> não existe ou não transclui o editnotice do país correspondente. Por favor, adicione o código <code>{{:MediaWiki:Editnotice-0-".$variation["0"]."}}</code> neste editnotice.</b></ul>");
	}
	foreach ($embedded as $embed) {
		$embed_talkpage = substr_replace($embed, "Discussão:", 0, 23);
		if (in_array($embed_talkpage, $articles)) continue;
		$embed_talkpage = str_replace("-", "/", $embed_talkpage);
		if (in_array($embed_talkpage, $articles)) continue;
		echo ("<ul><b>ERRO: O editnotice <a href='https://pt.wikipedia.org/w/index.php?title=".urlencode($embed)."'>".$embed."</a> existe mas a discussão do seu artigo não aparenta estar categorizada. Por favor, adicione a predefinição da categorização ou elimine a editnotice.</b></ul>");
	}
}


