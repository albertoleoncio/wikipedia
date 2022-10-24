<?php
require_once './bin/globals.php';

//Login
require_once './bin/api.php';
loginAPI($usernameBQ, $passwordBQ);

//Levanta lista de reversores
$rollbackers_API = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=allusers&augroup=rollbacker&aulimit=500");
$rollbackers_API = unserialize($rollbackers_API)["query"]["allusers"];

//Insere ID de reversores em uma array
$rollbackers_IDs = array();
foreach ($rollbackers_API as $user) $rollbackers_IDs[] = $user["userid"];

//Levanta lista de bloqueios ocorridos nos últimos 30 minutos
$blocks_API = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=logevents&leprop=userid%7Cdetails%7Cids%7Ctitle%7Cuser%7Ctype%7Ctimestamp&letype=block&lelimit=500&letype=block&ledir=older&leend=".urlencode(gmdate('Y-m-d\TH:i:s\Z', strtotime("-30 minutes"))));
$blocks_API = unserialize($blocks_API)["query"]["logevents"];

//Cria array para armazenar casos para notificação
$notify = array();

//Processa cada registro de bloqueio
foreach ($blocks_API as $key => $log) {

	//Verifica se autor é reversor
	if (in_array($log["userid"], $rollbackers_IDs)) {

		//Recupera nome do alvo
		$target = explode(":", $log["title"]);

		//Verifica privilégios do alvo
		$target_API = file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=php&list=users&usprop=rights&ususers=".urlencode($target["1"]));
		$target_API = unserialize($target_API)["query"]["users"]["0"]["rights"];

		//Verifica se alvo é autoconfirmado
		if (in_array("editsemiprotected", $target_API)) {
			$notify[] = [
				"id"		=> $log["logid"],
				"user"		=> $log["user"],
				"target"	=> $target["1"],
				"problem"	=> "autoconfirmado"
			];
			continue;			
		}

		//Ignora desbloqueios de não-autoconfirmados
		if ($log["action"] == "unblock") continue;

		//Verifica se bloqueio foi infinito e armazena caso sim
		if (!isset($log["params"]["expiry"])) {
			$notify[] = [
				"id"		=> $log["logid"],
				"user"		=> $log["user"],
				"target"	=> $target["1"],
				"problem"	=> "infinito"
			];
			continue;
		}

		//Verifica se bloqueio é superior a 24 horas
		$lenght = strtotime($log["params"]["expiry"]) - strtotime($log["timestamp"]);
		if ($lenght > 86401) {
			$notify[] = [
				"id"		=> $log["logid"],
				"user"		=> $log["user"],
				"target"	=> $target["1"],
				"problem"	=> ($lenght/3600)." horas"
			];
			continue;
		}
	}
}

//Define páginas
$page = "Wikipédia:Pedidos/Notificação de incidentes";
$done_page = "Usuário(a):BloqBot/rev";

//Recupera lista de incidentes já lançados
$done_list = explode("\n", file_get_contents("https://pt.wikipedia.org/w/index.php?title=Usu%C3%A1rio(a):BloqBot/rev&action=raw"));

//Loop para inserir incidentes na página
foreach ($notify as $case) {

	//Verifica se pedido já foi lançado e continua para o próximo pedido caso sim
	if (in_array($case["id"], $done_list)) continue;

	//Recupera codigo-fonte da página
	$html = getAPI($page);

	//Insere pedido no código
	$html = $html."\n{{subst:Incidente/Bloqbot|".$case["user"]."|".$case["problem"]."|".$case["target"]."|".$case["id"]."}}\n";

	//Gravar código
	editAPI($html, NULL, FALSE, "bot: Inserindo notificação de incidente envolvendo reversor", $page, $usernameBQ);

	//Recupera codigo-fonte da lista de incidentes já lançados
	$done_html = getAPI($done_page);

	//Gravar código na lista de incidentes já lançados
	editAPI($done_html."\n".$case["id"], NULL, FALSE, "bot: Lançando ID de incidente", $done_page, $usernameBQ);
}

echo("Concluído!");