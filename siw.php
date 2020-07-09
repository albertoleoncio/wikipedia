<?php
echo "<pre>";

//Verifica se alguma página foi informada
if ($_GET["artigo_titulo"]) {

	//Introdução da ferramenta
	echo "Editores do artigo <b>".trim($_GET["artigo_titulo"])."</b>:<br><small>Inativo: +90 dias</small><br><br>";

	//Coleta nome dos usuários não-bot que editaram o artigo
	$editores_artigo = pos(json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&prop=contributors&titles=".urlencode(trim($_GET["artigo_titulo"]))."&pcexcluderights=bot&pclimit=max"), true)['query']['pages'])['contributors'];

	//Loop de verificação dos usuários
	foreach ($editores_artigo as $list) {

		//Coleta informações do usuário
		$user = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=users%7Cusercontribs&usprop=blockinfo&uclimit=1&ususers=".urlencode($list['name'])."&ucuser=".urlencode($list['name'])), true)['query'];
		
		//Verifica se usuário está bloqueado e encerra loop em caso positivo
		if (isset($user['users'][0]['blockid']) AND !isset($user['users'][0]['blockpartial'])) {
			$block = TRUE;
			echo $list['name']." <small>(bloqueado)</small><br>";
			continue;
		}
		
		//Retorna link para envio de aviso
		echo "<a target='_blank' href='https://pt.wikipedia.org/w/index.php?title=User_talk:".urlencode($list['name'])."&action=edit&section=new&preloadtitle=".urlencode("novo tópico: → [[".$list['name']."]] ([[WP:ESR-SIW]])")."&preload=Predefini%C3%A7%C3%A3o:Aviso-ESR-SIW/Preload&preloadparams%5b%5d=".urlencode(trim($_GET["artigo_titulo"]))."&preloadparams%5b%5d='>".$list['name']."</a>";

		//Verifica se usuário está inativo e comenta isso ao lado do nome dele se positivo
		if ((date("U", strtotime($user['usercontribs'][0]['timestamp'])) + 7776000) < time()) {
			$dias_inativo = round((time() - date("U", strtotime($user['usercontribs'][0]['timestamp']))) / 86400);
			echo " <small>(inativo há ".$dias_inativo." dias)</small>";
		}

		echo "<br>";
	}
}

//Formulário de submissão do nome do artigo
?><br>
<form action="/alberobot/siw.php" method="get">
  <input type="text" placeholder="Nome do artigo" name="artigo_titulo">
  <input type="submit">
</form>
<br>
<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
