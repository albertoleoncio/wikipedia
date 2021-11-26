<pre><?php
include './bin/globals.php';

//Define fuso horário como UTC
date_default_timezone_set('UTC');

//Define data atual
$timestamp_now = strtotime('today');

//Define $dados como uma array
$dados = array();

//Login
include './bin/api.php';
loginAPI($username, $password);

//Define página de propostas
$pageA = "Wikipédia:Eventos atuais/Propostas";
$htmlA = getsectionsAPI($pageA);

//Loop para análise de cada seção
foreach ($htmlA as $key => $section) {

	//Pula seção inicial
	if ($key == "0") continue;

	//Coleta informações da seção
	preg_match_all('/\n: *{{[Cc]oncordo}}/', 							$section, $section_concordo);
	preg_match_all('/\n: *{{[Dd]iscordo}}/', 							$section, $section_discordo);
	preg_match_all('/\| *timestamp *= *(\d*)/', 						$section, $section_timestamp);
	preg_match_all('/\| *bot *= *(\w)/', 								$section, $section_bot);
	preg_match_all('/\| *texto *= *([^\n]*)/',							$section, $section_texto);
	preg_match_all('/\'\'\'\[\[([^\]\|\#]*)|\[\[([^\|]*)\|\'\'\'/',		$section_texto["1"]["0"], $section_article);
	$section_article = $section_article[2][0].$section_article[1][0];

	//Pula seção caso marcador de bot esteja desativado
	if ($section_bot["1"]["0"] != "s") continue;

	//Prepara variáveis para condicionais
	$run = false;
	$section_time_passed = $timestamp_now - $section_timestamp["1"]["0"];
	$concordo = count($section_concordo["0"]);
	$discordo = count($section_discordo["0"]);

	//Loop para analisar situação da proposta
	if ($section_time_passed < 7200) { 			//Menos de 2 horas
		continue;
	} elseif ($section_time_passed < 14400) { 	//2 horas
		if ($discordo > 0) continue;
		if ($concordo >= 5) $run = true;
	} elseif ($section_time_passed < 21600) { 	//4 horas
		if ($discordo > 0) continue;
		if ($concordo >= 3) $run = true;
	} elseif ($section_time_passed < 28800) { 	//6 horas
		if ($discordo > 0) continue;
		if ($concordo >= 1) $run = true;
	} else { 									//8 horas
		if ($discordo == 0){ 					//8 horas sem discordos
			$run = true;
		} elseif ($concordo == 0) { 			//8 horas com discordos e sem concordos
			continue;
		} else {								//8 horas com discordos e concordos
			if ($concordo / ($concordo + $discordo) >= 0.75) $run = true; 
		}
	}

	//Código para ser executado na atualização
	if ($run) {

		///////////////
		//
		//	Wikipédia:Eventos atuais/Propostas
		//
		///////////////

		//Altera marcador de bot
		preg_replace('/\| *bot *= *(\w)/', '|bot = p', $section);

		//Salva edição
		$diff = editAPI($section, $key, FALSE, "bot: (1/4) Marcando proposta como publicada", $pageA, $username);


		///////////////
		//
		//	Predefinição:Eventos atuais
		//
		///////////////

		//Define página principal
		$pageB = "Predefinição:Eventos atuais";
		$htmlB = getAPI($pageB);

		//Explode código
		$htmlB_sections = explode("\n<!-- % -->\n", $htmlB);
		$htmlB_events = explode("\n", $htmlB_sections["1"]);

		//Insere novo evento aprovado
		array_unshift($htmlB_events, "*<!-- ".utf8_encode(strftime('%e de %B de %Y', $timestamp_now))." --> ".$section_texto["1"]["0"]);

		//Remove evento mais antigo
		$recente = end($htmlB_events);
		array_pop($htmlB_events);

		//Remonta código
		$htmlB_sections["1"] = implode("\n", $htmlB_events);
		$htmlB = implode("\n<!-- % -->\n", $htmlB_sections);

		//Salva página
		editAPI($htmlB, NULL, FALSE, "bot: (2/4) Publicando nova proposta", $pageB, $username);


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
		editAPI($htmlC, NULL, FALSE, "bot: (3/4) Inserido proposta recente", $pageC, $username);


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
		editAPI($htmlD, 0, FALSE, "bot: (4/4) Inserindo EvRdiscussão", $pageD, $username);
	}
}


echo("\nOK!");
?>