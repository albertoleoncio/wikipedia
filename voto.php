<?php

//Define UTC como timezone
date_default_timezone_set('UTC');

//Verifica se alguma usuário foi informado
if ($_GET["user"]) {

	// Condicionais para verificação de data/hora informada no formulário
	// ╔══════╦══════╦════════════════════════════════════════════════╗
	// ║ DATA ║ HORA ║ TRATAMENTO                                     ║
	// ╠══════╬══════╬════════════════════════════════════════════════╣
	// ║  S   ║  S   ║ Utiliza data/hora informada                    ║
	// ╠══════╬══════╬════════════════════════════════════════════════╣
	// ║  S   ║  N   ║ Define como o último segundo da data informada ║
	// ╠══════╬══════╬════════════════════════════════════════════════╣
	// ║      ║      ║ Verifica se horário informado é inferior ao    ║
	// ║  N   ║  S   ║ horário UTC atual. Se sim, considera o dia     ║
	// ║      ║      ║ atual, se não, considera o dia anterior.       ║
	// ╠══════╬══════╬════════════════════════════════════════════════╣
	// ║  N   ║  N   ║ Utiliza data/hora UTC atual                    ║
	// ╚══════╩══════╩════════════════════════════════════════════════╝
	//
	if ((!isset($_GET["date"]) OR $_GET["date"] == "") AND (!isset($_GET["time"]) OR $_GET["time"] == "")) {
		$date = date("o-m-d");
		$time = date("H:i:s");
	} elseif (isset($_GET["date"]) AND (!isset($_GET["time"]) OR $_GET["time"] == "")) {
		$date = $_GET["date"];
		$time = "23:59:59";
	} elseif ((!isset($_GET["date"]) OR $_GET["date"] == "") AND isset($_GET["time"])) {
		$time = $_GET["time"];
		if ($time < date("H:i:s")) {
			$date = date("o-m-d");
		} else {
			$date = date("o-m-d", strtotime($_GET["date"]." -1 day"));
		}
	} elseif (isset($_GET["date"]) AND isset($_GET["time"])) {
		$date = $_GET["date"];
		$time = $_GET["time"];
	} else {
		$date = date("o-m-d");
		$time = date("H:i:s");
	}

	//Captura nome de usuário
	$user = trim($_GET["user"]);

	//Coleta últimas 301 edições do usuário no domínio principal
	$userquery = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&uclimit=301&format=json&list=usercontribs&ucuser=".urlencode($user)."&ucstart=".urlencode($date)."T".urlencode($time)."Z&ucnamespace=0"), true)['query']['usercontribs'];

	//Contabiliza edições
	if (count($userquery) == "301") {
		$count = "300+ (Possui direito ao voto)";
	} elseif (count($userquery) == "300") {
		$count = "300 (Possui direito ao voto)";
	}
	else {
		$count = count($userquery)." (Não possui direito ao voto)";
	}
}
?><!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>Direiro ao voto</title>
	</head>
	<body>
		<h1>Direito ao voto</h1>
		<form action="/alberobot/voto.php" method="get">
			<label>Usuário: </label>
			<input <?php if (isset($user) OR $user != "") echo "value='".$user."'"; ?> type="text" name="user">
			<br>
			<br>
			<label>Data: </label>
			<input <?php if (isset($date) OR $date != "") echo "value='".$date."'"; ?> type="date" name="date">
			<label>Hora: </label>
			<input <?php if (isset($time) OR $time != "") echo "value='".$time."'"; ?> type="time" step="1" max="23:59:59" name="time">
			<br>
			<br>
			<small>Por favor insira a data e hora do começo da votação como Tempo Universal Coordenado (UTC).</small>
			<br>
			<input type="submit" value="Verificar">
		</form>
		<hr>
		<?php if ($_GET["user"]) echo "<pre>Total de edições: ".$count."<br><br><small>Usuário: ".$_GET["user"]."<br>Até ".$date." ".$time." UTC.</small></pre><br>"; ?>
		<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
	</body>
</html>