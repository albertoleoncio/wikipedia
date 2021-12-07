<pre><?php
include './bin/globals.php';

//Define fuso horário como UTC
date_default_timezone_set('UTC');

//Define data atual
$timestamp_now = time();

//Define $dados como uma array
$dados = array();

//Login
include './bin/api.php';
loginAPI($usernameEA, $passwordEA);
require "tpar/twitteroauth/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;

//Define página de propostas
$pageA = "Wikipédia:Eventos atuais/Propostas";
$htmlA = getsectionsAPI($pageA);

//Loop para análise de cada seção
foreach ($htmlA as $key => $section) {

	//Pula seção inicial
	if ($key == "0") continue;

	//Coleta informações da seção
	preg_match_all('/{{[Cc]oncordo}}/', 		$section, $section_concordo);
	preg_match_all('/{{[Dd]iscordo}}/', 		$section, $section_discordo);
	preg_match_all('/\| *timestamp *= *(\d*)/', $section, $section_timestamp);
	preg_match_all('/\| *imagem *= *([^\n]*)/', $section, $section_image);
	preg_match_all('/\| *bot *= *(\w)/', 		$section, $section_bot);
	preg_match_all('/\| *texto *= *([^\n]*)/',	$section, $section_texto);
	preg_match_all('/\| *artigo *= *([^\n]*)/',	$section, $section_article);

	//Pula seção caso marcador de bot esteja desativado
	if ($section_bot["1"]["0"] != "s") continue;

	//Prepara variáveis para condicionais
	$approved = false;
	$declined = false;
	$section_time_passed = $timestamp_now - $section_timestamp["1"]["0"];
	$concordo = count($section_concordo["0"]);
	$discordo = count($section_discordo["0"]);
	$section_article = $section_article["1"]["0"];

	//Echos
	echo("\n".$section_article.": ");
	echo (bcdiv($section_time_passed, 60, 0)." minutos");
	echo (" / ");
	echo ($concordo." concordos");
	echo (" / ");
	echo ($discordo." discordos");
	echo (" => ");

	//Loop para analisar situação da proposta
	if ($section_time_passed < 7200) { 			//Menos de 2 horas
		echo("<b><2</b>");
		continue;
	} elseif ($section_time_passed < 14400) { 	//2 horas
		echo("<b>2</b>");
		if ($discordo > 0) continue;
		if ($concordo >= 5) $approved = true;
	} elseif ($section_time_passed < 21600) { 	//4 horas
		echo("<b>4</b>");
		if ($discordo > 0) continue;
		if ($concordo >= 3) $approved = true;
	} elseif ($section_time_passed < 28800) { 	//6 horas
		echo("<b>6</b>");
		if ($discordo > 0) continue;
		if ($concordo >= 1) $approved = true;
	} else { 									//8 horas
		echo("<b>8 ");
		if ($discordo == 0){ 					//8 horas sem discordos
			echo("?</b>");
			$approved = true;
		} else {								//8 horas com discordos e concordos
			if ($concordo / ($concordo + $discordo) >= 0.75) {
				echo("OOX</b>");
				$approved = true; 
			} else {
				echo("OXX</b>");
				$declined = true;
			}
		}
	}

	//Código para ser executado na atualização em caso de aprovação
	if ($approved) {
		echo("APPROVED ");

		///////////////
		//
		//	Wikipédia:Eventos atuais/Propostas
		//
		///////////////

		//Altera marcador de bot
		$section = preg_replace('/\| *bot *= *\w/', '|bot = p |em = '.$timestamp_now, $section);

		//Salva edição
		editAPI($section, $key, FALSE, "bot: (1/4) Marcando proposta como publicada", $pageA, $usernameEA);


		///////////////
		//
		//	Predefinição:Eventos atuais
		//
		///////////////

		//Define página principal
		$pageB = "Predefinição:Eventos atuais";
		$htmlB = getAPI($pageB);

		//Explode código e remove marcador de imagem no texto
		$htmlB_sections = explode("\n<!-- % -->\n", $htmlB);
		$htmlB_sections["1"] = preg_replace('/\(\'\'[^\']*?\'\'\) |\'\'\([^\)]*\)\'\' /', '', $htmlB_sections["1"]);
		$htmlB_events = explode("\n", $htmlB_sections["1"]);

		//Insere novo evento aprovado
		array_unshift($htmlB_events, "*<!-- ".utf8_encode(strftime('%e de %B de %Y', $timestamp_now))." --> ".$section_texto["1"]["0"]);

		//Remove evento mais antigo
		$recente = end($htmlB_events);
		array_pop($htmlB_events);

		//Remonta código
		$htmlB_sections["1"] = implode("\n", $htmlB_events);
		$htmlB = implode("\n<!-- % -->\n", $htmlB_sections);

		//Insere imagem
		$image = file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&format=php&prop=info&titles=".rawurlencode("File:".trim($section_image["1"]["0"])));
		$image = end(unserialize($image)["query"]["pages"]);
		if (isset($image["lastrevid"])) $htmlB = preg_replace(
			'/<imagemap>[^>]*?<\/imagemap>/', 
			"<imagemap>\nFicheiro:".$section_image["1"]["0"]."|125x175px|borda|direita\ndefault [[".$section_article."]]\n</imagemap>", 
			$htmlB
		);

		//Salva página
		$diff = editAPI($htmlB, NULL, FALSE, "bot: (2/4) Publicando nova proposta", $pageB, $usernameEA);


		///////////////
		//
		//	Predefinição:Ea-notícias
		//
		///////////////

		//Define página de recentes
		$pageC = "Predefinição:Ea-notícias";
		$htmlC = getAPI($pageC);

		//Insere novo evento recente
		$htmlC = preg_replace('/<\/span><\/div>/', "</span></div>\n".preg_replace('/<!--+ *|(?<=-)-+>/', '', $recente), $htmlC);

		//Salva página
		editAPI($htmlC, NULL, FALSE, "bot: (3/4) Inserido proposta recente", $pageC, $usernameEA);


		///////////////
		//
		//	Discussão:Artigo
		//
		///////////////

		//Verifica se página é redirect
		$section_article_renamed = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&titles=".urlencode($section_article)."&redirects=1"), TRUE);
		if (isset($section_article_renamed["query"]["redirects"])) $section_article = $section_article_renamed["query"]["redirects"]["0"]["to"];

		//Define página
		$pageD = "Discussão:".$section_article;

		//Recupera dados da seção inicial da página de discussão do artigo-chave
		$htmlD = getsectionsAPI($pageD)['0'];

		//Insere nova predefinição no final da seção
		$htmlD = $htmlD."\n{{EvRdiscussão|data1=".utf8_encode(strftime('%e de %B de %Y', $timestamp_now))."|oldid1=".$diff."}}";

		//Grava página
		editAPI($htmlD, 0, FALSE, "bot: (4/4) Inserindo EvRdiscussão", $pageD, $usernameEA);

		///////////////
		//
		//	User:$username/log
		//
		///////////////

		//Define página
		$pageE = "User:EventosAtuaisBot/log";

		//Grava página
		editAPI($section_texto["1"]["0"], NULL, FALSE, "bot: (log) Registrando último evento aprovado", $pageE, $usernameEA);

		///////////////
		//
		//	Twitter
		//
		///////////////

		//Monta status para envio ao Twitter
		$twitter_status = preg_replace('/ *<!--(.*?)--> */', '', $section_texto["1"]["0"]);
		$twitter_status = preg_replace('/\'|\[\[[^\|\]]*\||\]|\[\[/', '', $twitter_status);
		$twitter_status = $twitter_status."\n\nEsse é um evento recente ou em curso que está sendo acompanhado por nossas voluntárias e voluntários. Veja mais detalhes no link: https://pt.wikipedia.org/w/index.php?title=".rawurlencode($section_article);

		//Envia Tweet
		define('CONSUMER_KEY', $twitter_consumer_key);
		define('CONSUMER_SECRET', $twitter_consumer_secret);
		$twitter_conn = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $twitter_access_token, $twitter_access_token_secret);
		$post_tweets = $twitter_conn->post("statuses/update", ["status" => $twitter_status]);
	
	} elseif ($declined) {
		echo("DECLINED ");

		///////////////
		//
		//	Wikipédia:Eventos atuais/Propostas
		//
		///////////////

		//Altera marcador de bot
		$section = preg_replace('/\| *bot *= *\w/', '|bot = r |em = '.$timestamp_now, $section);

		//Salva edição
		editAPI($section, $key, FALSE, "bot: Marcando proposta como recusada", $pageA, $usernameEA);
	}

}


echo("\n\nExecutado!");
?>