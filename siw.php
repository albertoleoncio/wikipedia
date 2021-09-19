<!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>ESR-SIW</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./tpar/w3.css">
	</head>
	<body>
		<div class="w3-container" id="menu">
			<div class="w3-content" style="max-width:800px">
				<h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">ESR-SIW</span></h5>
				<div class="w3-row-padding w3-center w3-margin-top">
					<div class="w3-half">
						<form action="/siw.php" method="get">
							<div class="w3-container w3-padding-48 w3-card">
		      					<p class="w3-center w3-wide">ARTIGO</p>
		      					<p class="w3-text-grey">
		      						<input class="w3-input w3-padding-16 w3-border" <?php if (isset($_GET["artigo_titulo"]) OR $_GET["artigo_titulo"] != "") echo "value='".$_GET["artigo_titulo"]."'"; ?> type="text" name="artigo_titulo">
		      					</p>
		      					<br>
		      					<p class="w3-center w3-wide">USUÁRIOS INATIVOS</p>
		      					<p class="w3-text-grey">
			      					<select class="w3-select w3-border" name="inativo">
										<option value="" disabled>Selecione...</option>
										<option value="1"<?php if ($_GET["artigo_titulo"] AND $_GET["inativo"] == 1) echo " selected"; ?>>Incluir todos os usuários</option>
										<option value="2"<?php if ($_GET["artigo_titulo"] AND $_GET["inativo"] == 2) echo " selected"; ?>>Remover inativos há 3 meses</option>
										<option value="3"<?php if ($_GET["artigo_titulo"] AND $_GET["inativo"] == 3) echo " selected"; ?>>Remover inativos há 6 meses</option>
										<option value="4"<?php if ($_GET["artigo_titulo"] AND $_GET["inativo"] == 4) echo " selected"; ?>>Remover inativos há 1 ano</option>
										<option value="5"<?php if ($_GET["artigo_titulo"] AND $_GET["inativo"] == 5) echo " selected"; ?>>Remover inativos há 5 anos</option>
									</select>
								</p>
		      					<br>
								<p class="w3-center w3-wide">EDIÇÕES MENORES</p>
		      					<p>
		      						<input name="menor" class="w3-check" type="checkbox"<?php if ($_GET["artigo_titulo"] AND isset($_GET["menor"])) echo " checked"; ?>>
		      						<label>Excluir edições menores</label>
		      					</p>
		      					<br>
		      					<p>
			      					<button class="w3-button w3-block w3-black w3-margin-top" type="submit">Verificar</button>
			      				</p>
		      				</div>
		      			</form>
		      		</div>
		      		<div class="w3-half">
		      			<div class="w3-container w3-padding-48 w3-card">
		      					
<?php
//Verifica se alguma página foi informada
if ($_GET["artigo_titulo"]) {

	//Introdução da lista
	echo 	"<p class='w3-center w3-wide'>EDITORES DO ARTIGO</p>
			<h3 class='w3-center'><b>".trim($_GET["artigo_titulo"])."</b></h3>
			<small>Ao clicar, uma nova janela será aberta para o envio da mensagem. Em seguida, clique em \"Publicar alterações\".</small>
			<br><br>
			<ul class='w3-ul w3-hoverable w3-border'>";

	//Coleta nome dos usuários que editaram o artigo, excluindo os bots
	$editores_artigo = pos(json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&prop=contributors&titles=".urlencode(trim($_GET["artigo_titulo"]))."&pcexcluderights=bot&pclimit=max"), true)['query']['pages'])['contributors'];

	//Coleta revisões do artigo, para detectar revisões menores
	if (isset($_GET["menor"])) {
		$no_minor = array();
		$revisoes = pos(json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&prop=revisions&titles=".urlencode(trim($_GET["artigo_titulo"]))."&rvprop=user%7Cflags&rvlimit=max"), true)['query']['pages'])['revisions'];
		foreach ($revisoes as $revisao) {
			if (isset($revisao['anon']) OR isset($revisao['minor'])) continue;
			$no_minor[$revisao['user']] = true;
		}
	}

	//Coleta lista de usuários descadastrados
	$desc = explode("\n#", file_get_contents("https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:Aviso-ESR-SIW/Descadastro&action=raw&section=1"));
	unset($desc[0]);

	//Loop de verificação dos usuários
	foreach ($editores_artigo as $usuario) {

		//Coleta informações do usuário
		$usercontribs = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=query&format=json&list=users%7Cusercontribs&usprop=blockinfo&uclimit=1&ususers=".urlencode($usuario['name'])."&ucuser=".urlencode($usuario['name'])), true)['query'];
		
		//Verifica se usuário está bloqueado e encerra loop em caso positivo
		if (isset($usercontribs['users'][0]['blockid']) AND !isset($usercontribs['users'][0]['blockpartial'])) continue;

		//Verifica se há edições não-menores do usuário no artigo e se a opção foi selecionada, encerrando loop em caso positivo
		if (isset($_GET["menor"]) AND !isset($no_minor[$usuario['name']])) continue;

		//Verifica se usuário está na lista de descadastro, encerrando loop em caso positivo
		if (in_array($usuario['name'], $desc)) continue;

		//Verifica se usuário está inativo e contabiliza os dias após a ultima edição. 
		//Se a opção de exclusão dos inativos for selecionada, encerra loop de acordo com a opção
		if ((date("U", strtotime($usercontribs['usercontribs'][0]['timestamp'])) + 7776000) < time()) {
			$dias_inativo = round((time() - date("U", strtotime($usercontribs['usercontribs'][0]['timestamp']))) / 86400);
			if ($_GET["inativo"] == 5 AND $dias_inativo >= 1825) continue;
			if ($_GET["inativo"] == 4 AND $dias_inativo >= 365) continue;
			if ($_GET["inativo"] == 3 AND $dias_inativo >= 180) continue;
			if ($_GET["inativo"] == 2 AND $dias_inativo >= 90) continue;
		} else $dias_inativo = false;
		
		//Retorna link para envio de aviso
		echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\"><a href=\"https://pt.wikipedia.org/w/index.php?title=User_talk:".urlencode($usuario['name'])."&action=edit&section=new&preloadtitle=".urlencode("[[".$_GET["artigo_titulo"]."]] ([[WP:ESR-SIW]])")."&preload=Predefini%C3%A7%C3%A3o:Aviso-ESR-SIW/Preload&preloadparams%5b%5d=".urlencode(trim($_GET["artigo_titulo"]))."&preloadparams%5b%5d='" target="_blank">.$usuario['name']</a></li>";
		if ($dias_inativo > 90) echo " <small>(inativo há ".$dias_inativo." dias)</small>";
		echo "</li>";
	}
} else echo "Preencha o formulário ao lado";
						?></div>
		      		</div>
		      	</div>
      		</div>
      	</div>
		<hr>
		<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
	</body>
</html>
