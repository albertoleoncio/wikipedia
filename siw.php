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
		      					<p class="w3-center w3-wide">EDITORES INATIVOS</p>
		      					<p class="w3-text-grey">
			      					<select class="w3-select w3-border" name="inativo">
										<option value="" disabled>Selecione...</option>
										<option value="1"<?php if ($_GET["artigo_titulo"] AND $_GET["inativo"] == 1) echo " selected"; ?>>Incluir todos os editores</option>
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

//Função para usar API do MediaWiki
function api_get($params) {
	$ch1 = curl_init( "https://pt.wikipedia.org/w/api.php?" . http_build_query( $params ) );
	curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
	$data = curl_exec( $ch1 );
	curl_close( $ch1 );
	return $data;
}

//Verifica se alguma página foi informada
if ($_GET["artigo_titulo"]) {

	//Coleta nome dos usuários que editaram o artigo, excluindo os bots
	$contributors_params = [
		"action"          => "query",
		"format"          => "php",
		"prop"            => "contributors",
		"titles"          => trim($_GET["artigo_titulo"]),
		"pcexcluderights" => "bot",
		"pclimit"         => "max"
	];
	$contributors = pos(unserialize(api_get($contributors_params))['query']['pages']);

	//Verifica se artigo não existe
	if (isset($contributors['missing'])) {
		echo "<h3 class='w3-center' style='hyphens: auto;'><b>Artigo ".trim($_GET["artigo_titulo"])." não existe!</b></h3>";
	} else {
		//Introdução da lista
		echo 	"<p class='w3-center w3-wide'>EDITORES DO ARTIGO</p>
				<h3 class='w3-center' style='hyphens: auto;'><b>".trim($_GET["artigo_titulo"])."</b></h3>
				<small>Ao clicar, uma nova janela será aberta para o envio da mensagem. Em seguida, clique em \"Publicar alterações\", ou use o atalho ALT+SHIFT+S.</small>
				<br><br>
				<ul class='w3-ul w3-hoverable w3-border'>";
			
		//Coloca nomes dos usuários na array
		$contributors = $contributors['contributors'];

		//Coleta revisões do artigo, para detectar revisões menores
		if (isset($_GET["menor"])) {
			$no_minor = array();
            $revisions_params = [
                "action"    => "query",
                "format"    => "php",
                "prop"      => "revisions",
                "titles"    => trim($_GET["artigo_titulo"]),
                "rvprop"    => "user|flags",
                "rvlimit"   => "max"
            ];
			$revisions = pos(unserialize(api_get($revisions_params))['query']['pages'])['revisions'];
			foreach ($revisions as $rev) {
				if (isset($rev['anon']) OR isset($rev['minor'])) continue;
				$no_minor[$rev['user']] = true;
			}
		}

		//Coleta lista de editores descadastrados
        $optout_params = [
            "action"    => "query",
            "format"    => "php",
            "prop"      => "revisions",
            "titles"    => "Predefinição:Aviso-ESR-SIW/Descadastro",
            "rvprop"    => "content",
            "rvslots"   => "main",
            "rvsection" => "1"
        ];
		$optout = explode("\n#", unserialize(api_get($optout_params))["query"]["pages"]["6352119"]["revisions"]["0"]["slots"]["main"]["*"]);
		unset($optout[0]);

		//Loop de verificação dos editores
		foreach ($contributors as $user) {

			//Coleta informações do usuário
            $usercontribs_params = [
                "action"    => "query",
                "format"    => "php",
                "list"      => "users|usercontribs",
                "pageids"   => "6352119",
                "usprop"    => "blockinfo",
                "ususers"   => $user['name'],
                "ucuser"    => $user['name'],
                "uclimit"   => "1"
            ];
			$usercontribs = unserialize(api_get($usercontribs_params))['query'];
			
			//Verifica se usuário está bloqueado e encerra loop em caso positivo
			if (isset($usercontribs['users']["0"]['blockid']) AND !isset($usercontribs['users']["0"]['blockpartial'])) continue;

			//Verifica se há edições não-menores do usuário no artigo e se a opção foi selecionada, encerrando loop em caso positivo
			if (isset($_GET["menor"]) AND !isset($no_minor[$user['name']])) continue;

			//Verifica se usuário está na lista de descadastro, encerrando loop em caso positivo
			if (in_array($user['name'], $optout)) continue;

			//Verifica se usuário está inativo e contabiliza os dias após a ultima edição. 
			//Se a opção de exclusão dos inativos for selecionada, encerra loop de acordo com a opção
			if ((date("U", strtotime($usercontribs['usercontribs']["0"]['timestamp'])) + 7776000) < time()) {
				$days_inactive = round((time() - date("U", strtotime($usercontribs['usercontribs']["0"]['timestamp']))) / 86400);
				if ($_GET["inativo"] == 5 AND $days_inactive >= 1825) continue;
				if ($_GET["inativo"] == 4 AND $days_inactive >= 365) continue;
				if ($_GET["inativo"] == 3 AND $days_inactive >= 180) continue;
				if ($_GET["inativo"] == 2 AND $days_inactive >= 90) continue;
			} else $days_inactive = false;
			
			//Retorna links individuais para envio de aviso
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\"><a style=\"text-decoration-line:none\" href=\"https://pt.wikipedia.org/w/index.php?title=User_talk:".urlencode($user['name'])."&action=edit&section=new&preloadtitle=".urlencode("[[".$_GET["artigo_titulo"]."]] ([[WP:ESR-SIW]])")."&preload=Predefini%C3%A7%C3%A3o:Aviso-ESR-SIW/Preload&preloadparams%5b%5d=".urlencode(trim($_GET["artigo_titulo"]))."&preloadparams%5b%5d=\" target=\"_blank\">".$user['name']."</a>";
			if ($days_inactive > 90) echo " <small>(inativo há ".$days_inactive." dias)</small>";
			echo "</li>";

			//Guarda nome do usuário para criar código do botão de avisar todos
			$contributors_js[] = $user['name'];
		}
		
		//Botão de abrir todas as páginas de editores de uma só vez
		if (count($contributors_js) != 0) {
			$open = '';
			foreach ($contributors_js as $user_js) {
		    	$open .= "window.open('https://pt.wikipedia.org/w/index.php?title=User_talk:".urlencode($user_js)."&action=edit&section=new&preloadtitle=".urlencode("[[".$_GET["artigo_titulo"]."]] ([[WP:ESR-SIW]])")."&preload=Predefini%C3%A7%C3%A3o:Aviso-ESR-SIW/Preload&preloadparams%5b%5d=".urlencode(trim($_GET["artigo_titulo"]))."&preloadparams%5b%5d=', '_blank');";
			}
			echo "<button type=\"button\" onclick=\"alert('Lembre-se de habilitar os pop-ups!');{$open}\">Avisar todos</button>";
		} else {
			echo "Encontrados ".count($contributors_js)." editores.";
		}
	}
} else echo "Preencha o formulário ao lado";
						?></div>
		      		</div>
		      	</div>
      		</div>
      	</div>
		<hr>
		<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
		<a href="https://github.com/albertoleoncio/wikipedia"><img src="data:image/png;base64,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" alt="Available on GitHub" style="height: 31px;margin-left: 5px;"></a>
	</body>
</html>
