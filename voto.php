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
		$date = date("o-m-d", strtotime($_GET["date"]));
		$time = "23:59:59";
	} elseif ((!isset($_GET["date"]) OR $_GET["date"] == "") AND isset($_GET["time"])) {
		$time = date("H:i:s", strtotime($_GET["time"]));
		if ($time < date("H:i:s")) {
			$date = date("o-m-d");
		} else {
			$date = date("o-m-d", strtotime($_GET["date"]." -1 day"));
		}
	} elseif (isset($_GET["date"]) AND isset($_GET["time"])) {
		$date = date("o-m-d", strtotime($_GET["date"]));
		$time = date("H:i:s", strtotime($_GET["time"]));
	} else {
		$date = date("o-m-d");
		$time = date("H:i:s");
	}

	//Captura nome de usuário
	$user = trim($_GET["user"]);

	//Coleta últimas 301 edições do usuário no domínio principal
	$userquery = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&uclimit=301&format=json&list=usercontribs&ucuser=".urlencode($user)."&ucstart=".urlencode($date)."T".urlencode($time)."Z&ucnamespace=0"), true)['query']['usercontribs'];

	//Coleta timestamp da primeira edição
	$timestamp = strtotime(json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=usercontribs&uclimit=1&ucuser=".urlencode($user)."&ucdir=newer&ucprop=timestamp"), true)['query']['usercontribs']['0']['timestamp']);
	if ($timestamp != FALSE AND $timestamp < strtotime($date."T".$time."Z -90 days")) $idade = TRUE;

	//Define cor do resultado
	if (count($userquery) >= "300" AND isset($idade)) {$color = "green";} else {$color = "red";}

	//Define resultado
	if (!isset($idade)) {
		$count = "Primeira edição recente<br>(Não possui direito ao voto)";
	} elseif (count($userquery) == "301") {
		$count = "Total de edições: 300+<br>(Possui direito ao voto)";
	} elseif (count($userquery) == "300") {
		$count = "Total de edições: 300<br>(Possui direito ao voto)";
	} else {
		$count = "Total de edições: ".count($userquery)."<br>(Não possui direito ao voto)";
	}
}
?><!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>Direiro a voto</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./tpar/w3.css">
	</head>
	<body>
		<div class="w3-container" id="menu">
			<div class="w3-content" style="max-width:800px">
				<h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">DIREITO A VOTO</span></h5>
				<div class="w3-row-padding w3-center w3-margin-top">
					<div class="w3-half">
						<form action="/alberobot/voto.php" method="get">
							<div class="w3-container w3-padding-48 w3-card">
		      					<p class="w3-center w3-wide">USUÁRIO</p>
		      					<p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" <?php if (isset($user) OR $user != "") echo "value='".$user."'"; ?> type="text" name="user"></p><br>
		      					<div class="w3-half">
			      					<p class="w3-center w3-wide">DATA</p>
			      					<p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" <?php if (isset($date) OR $date != "") echo "value='".$date."'"; ?> type="date" name="date"></p><br>
			      				</div>
			      				<div class="w3-half">
			      					<p class="w3-center w3-wide">HORA</p>
			      					<p class="w3-text-grey"><input class="w3-input w3-padding-16 w3-border" <?php if (isset($time) OR $time != "") echo "value='".$time."'"; ?> type="time" step="1" max="23:59:59" name="time"></p><br>
		      					</div>
		      					<small>Por favor insira a data e hora do começo da votação como Tempo Universal Coordenado (UTC).</small>
			      				<button class="w3-button w3-block w3-black" type="submit">Verificar</button>
		      				</div>
		      			</form>
		      		</div>
		      		<div class="w3-half">
		      			<div class="w3-container w3-padding-48 w3-card">
		      				<?php 
		      				if ($_GET["user"]) {
	  							if (isset($idade)){
	  								$percent = floor( (count($userquery) * 100) / 300 );
	  								echo "<div class='w3-light-grey'><div class='w3-container w3-".$color." w3-center' style='width:".$percent."%'>".$percent."%</div></div>";
								}
								echo "<p>".$count."</p>
								<p>Usuário: ".$_GET["user"]."<br>Em ".$date." ".$time." UTC</p>";
							} else {
								echo "Preencha o formulário ao lado";
							}?></div>
		      		</div>
		      	</div>
      		</div>
      	</div>
		<hr>
		<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
	</body>
</html>