<pre><?php
require_once './bin/globals.php';

//Login
require_once './bin/api.php';
loginAPI($usernameBQ, $passwordBQ);

//Levanta lista de autorrevisores
$autoreviers_API = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=allusers&augroup=autoreviewer%7Cbureaucrat%7Celiminator%7Csysop&aulimit=500");
$autoreviers_API = unserialize($autoreviers_API)["query"]["allusers"];

//Insere ID de autorrevisores em uma array
$autoreviers_IDs = array();
foreach ($autoreviers_API as $user) $autoreviers[] = $user["name"];

//Continua lista de autorrevisores
while (isset($autoreviers_API['continue'])) {
	$continue_autoreviers_API = $autoreviers_API['continue']['aufrom'];
	$autoreviers_API = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=allusers&augroup=autoreviewer%7Cbureaucrat%7Celiminator%7Csysop&aulimit=500&aufrom=".urlencode($continue_autoreviers_API));
	foreach ($autoreviers_API as $user) $autoreviers[] = $user["name"];
}

//Levanta lista de reversões ocorridos nos últimos 30 minutos
$rollbacks_API = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=recentchanges&rctag=mw-rollback&rcprop=title%7Cuser%7Cids%7Ccomment%7Ctags&rclimit=500&rcend=".urlencode(gmdate('Y-m-d\TH:i:s.000\Z', strtotime("-30 minutes"))));
$rollbacks_API = unserialize($rollbacks_API)["query"]["recentchanges"];

//Processa cada registro de reversão
$notify = array();
foreach ($rollbacks_API as $rollback) {

	//Recupera nome do alvo
	preg_match_all('/(?:Foi \[\[WP:REV\|revertida\]\] a edição|Foram \[\[WP:REV\|revertidas\]\] as edições) de \[\[Special:Contrib(?:s|uições|utions)\/\K[^\]\|]*/', $rollback["comment"], $rollbacked);

	//Pula caso a reversão tenha sido manual
	if (!isset($rollbacked["0"]["0"])) continue;

	//Pula caso tenha sido autorreversão
	if ($rollbacked["0"]["0"] == $rollback["user"]) continue;

	//Pula caso o revertido não seja autorrevisor
	if (!in_array($rollbacked["0"]["0"], $autoreviers)) continue;

	//Guarda dados da reversão
	$notify[] = [
		"id"		=> $rollback["revid"],
		"user"		=> $rollback["user"],
		"title"		=> $rollback["title"],
		"target"	=> $rollbacked["0"]["0"]
	];
}

//Define páginas
$page = "Usuário(a):BloqBot/Reversões";
$done_page = "Usuário(a):BloqBot/revd";

//Recupera lista de reversões já lançadas
$done_list = explode("\n", file_get_contents("https://pt.wikipedia.org/w/index.php?title=Usu%C3%A1rio(a):BloqBot/revd&action=raw"));

//Loop para inserir incidentes na página
foreach ($notify as $case) {

	//Pula caso reversão já tenha sido lançada
	if (in_array($case["id"], $done_list)) continue;

	//Recupera codigo-fonte da página
	$html = getAPI($page);

	//Insere pedido no código
	$html = $html."\n#{{dif|".$case["id"]."}}: Usuário '".$case["target"]."' revertido por '".$case["user"]."' na página [[:".$case["title"]."]].";

	//Gravar código
	editAPI($html, NULL, FALSE, "bot: Inserindo reversão não-usual", $page, $usernameBQ);

	//Recupera codigo-fonte da lista de reversões já lançadas
	$done_html = getAPI($done_page);

	//Gravar código na lista de reversões já lançadas
	editAPI($done_html."\n".$case["id"], NULL, FALSE, "bot: Lançando ID de reversão", $done_page, $usernameBQ);

	//Envia conteúdo para grupo no Telegram
	$telegram_context = array(
		'http' => array(
			'method' => 'POST',
			'header' => "Content-Type:application/x-www-form-urlencoded\r\n",
			'content' => http_build_query(
				array(
					'chat_id' => -1001169425230,
					'parse_mode' => 'MarkdownV2',
					'text' => "[\[Δ".$case["id"]."\]](https://pt.wikipedia.org/wiki/Special:diff/".$case["id"]."): [".$case["target"]."](https://pt.wikipedia.org/wiki/User:".$case["target"].") revertido por [".$case["user"]."](https://pt.wikipedia.org/wiki/User:".$case["user"].") em [".$case["title"]."](https://pt.wikipedia.org/wiki/".$case["title"].")\."
				)
			)
		)
	);
	$telegram_stream = stream_context_create($telegram_context);
	$telegram = file_get_contents('https://api.telegram.org/bot'.$TelegramToken.'/sendMessage', false, $telegram_stream);
	print_r($telegram);
}