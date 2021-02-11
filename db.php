<!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>Assistente de abertura de discussões de bloqueio</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./tpar/w3.css">
	</head>
	<body>
		<div class="w3-container" id="menu">
			<div class="w3-content" style="max-width:800px">
				<h5 class="w3-center w3-padding-48"><span class="w3-tag w3-wide">ASSISTENTE DE ABERTURA DE DISCUSSÕES DE BLOQUEIO</span></h5>
				<div class="w3-row-padding w3-center w3-margin-top">
					<div class="w3-half">
						<form action="/db.php" method="get">
							<div class="w3-container w3-padding-48 w3-card">
		      					<p class="w3-center w3-wide">NOME DO USUÁRIO</p>
		      					<p class="w3-text-grey">
		      						<input class="w3-input w3-padding-16 w3-border" <?php if (isset($_GET["conta"]) OR $_GET["conta"] != "") echo "value='".$_GET["conta"]."'"; ?> type="text" name="conta" placeholder="Usuário">
		      					</p>
		      					<br>
								<p class="w3-center w3-wide">VOCÊ É ADMINISTRADOR?</p>
		      					<p>
		      						<input name="sysop" class="w3-check" type="checkbox"<?php if ($_GET["artigo_titulo"] AND isset($_GET["sysop"])) echo " checked"; ?>>
		      						<label>Sim</label>
		      					</p>
		      					<br>
		      					<p class="w3-center w3-wide">EVIDÊNCIAS:</p>
		      					<p>
									<textarea class="w3-input w3-padding-16 w3-border" id="evidence" name="evidence" rows="4" cols="50" placeholder="Insira aqui as evidências para a solicitação do bloqueio. Utilize [[wikicode]] e não esqueça de assinar com ~~~~."></textarea>
		      					</p>
		      					<br>
		      					<p class="w3-center w3-wide">DEFESA:</p>
		      					<p>
									<textarea class="w3-input w3-padding-16 w3-border" id="defesa" name="defesa" rows="4" cols="50" placeholder="Insira aqui a defesa, caso fornecida, acompanhada do diff abaixo. Caso contrário, deixe ambos os campos em branco."></textarea>
		      					</p>
		      					<br>
		      					<p class="w3-center w3-wide">DIFF DA DEFESA:</p>
		      					<p class="w3-text-grey">
		      						<input class="w3-input w3-padding-16 w3-border" type="text" name="diff" placeholder="67890123">
		      					</p>
		      					<p>
			      					<button class="w3-button w3-block w3-black w3-margin-top" type="submit">Preparar lista de links</button>
			      				</p>
		      				</div>
		      			</form>
		      		</div>
		      		<div class="w3-half">
		      			<div class="w3-container w3-padding-48 w3-card">
		      				<ul class='w3-ul w3-hoverable w3-border'>
		      					
<?php
//Verifica se alguma conta foi informada
if ($_GET["conta"]) {
	$conta = $_GET["conta"];
	$sysop = $_GET["sysop"];
	$evidence = $_GET["evidence"];
	$defesa = $_GET["defesa"];
	$diff = $_GET["diff"];

	//Introdução da lista
	echo 	"<p class='w3-center w3-wide'>DISCUSSÃO DE BLOQUEIO</p>
			<h3 class='w3-center'><b>".trim($conta)."</b></h3>
			<small><b>Clique em cada link abaixo na ordem apresentada.</b> Ao clicar, uma nova janela será aberta para a edição da página. Em seguida, clique em \"Publicar alterações\".<br>Esta ferramenta está sujeita a erros, então não esqueça de verificar se as edições foram feitas corretamente.</small>
			<br><br>
			<ul class='w3-ul w3-hoverable w3-border'>";

	/*
    get_categories.php

    MediaWiki API Demos
    Demo of `Categories` module: Get categories associated with a page.

    MIT License
	*/

	$endPoint = "https://pt.wikipedia.org/w/api.php";
	$params = [
	    "action" => "query",
	    "format" => "json",
	    "prop" => "categories",
	    "titles" => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/".$conta
	];

	$ch = curl_init( $endPoint . "?" . http_build_query( $params ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$output = curl_exec( $ch );
	curl_close( $ch );

	$result = json_decode( $output, true )["query"]["pages"];

	if ($result['-1']) {
		//Criar página raiz
		echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?action=edit&preload=Template%3ADB1%2FPreload&title=Wikipedia%3APedidos+a+administradores%2FDiscuss%C3%A3o+de+bloqueio%2F".urlencode($conta)."&preloadparams%5B%5D=".urlencode($evidence)."&preloadparams%5B%5D=".urlencode("{{subst:#if:".$defesa."|{{citação2|1=".$defesa."|2={{subst:#if:".$diff."|{{dif|".$diff."}}}}}}}}")."', '_blank')\">Criar DB</li>\n";
		echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/Lista_de_pedidos?action=edit&section=new&nosummary=1&preload=Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/Lista_de_pedidos/Preload&preloadparams%5B%5D=".urlencode($conta)."&preloadparams%5B%5D=".urlencode($conta)."', '_blank')\">Publicar DB na lista de pedidos</li>\n";
		echo "<li class=\"w3-padding-small w3-left-align\"><textarea readonly rows='1' cols='2' style='resize: none;margin-bottom: -8px;' id='myInput2'>".str_replace("|BloqueioConcluídosTotal", "* [[Wikipédia:Pedidos a administradores/Discussão de bloqueio/".$conta."|".$conta."]]\n|BloqueioConcluídosTotal", preg_replace('/BloqueioAbertosTotal=(\d)/', 'BloqueioAbertosTotal={{subst:#expr:$1+1}}', file_get_contents("https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:MRConduta&action=raw")))."</textarea><button onclick='copyclip2()'>Copiar código do painel</button></li><script>function copyclip2(){var e=document.getElementById('myInput2');e.select(),e.setSelectionRange(0,99999),document.execCommand('copy')}</script>";
		echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:MRConduta&action=edit', '_blank')\">Colar (substituir) novo código no painel</li>";
		if ($sysop) {
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Especial:Mensagens_em_massa&spamlist=Wikip%C3%A9dia%3APedidos%2FDiscuss%C3%A3o+de+bloqueio%2FMassmessage&subject=Discuss%C3%A3o+de+bloqueio+de+".urlencode($conta)."&message=%7B%7Bsubst%3AUsu%C3%A1rio%3ATeles%2FMassMessage%2FDesbloqueio%7C".urlencode($conta)."%7C%7C~~~~~%7C%7D%7D', '_blank')\">Enviar mensagens em massa</li>";
		} else {
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Wikip%C3%A9dia:Pedidos/Outros?action=edit&section=new&preloadtitle=Mensagens+em+massa+para+discussão+de+bloqueio+do+usuário+".urlencode($conta)."&preload=Wikip%C3%A9dia:Pedidos/Outros/PreloadMassMessageDB&preloadparams%5B%5D=".urlencode($conta)."&preloadparams%5B%5D=".urlencode("https://pt.wikipedia.org/w/index.php?title=Especial:Mensagens_em_massa&spamlist=Wikip%C3%A9dia%3APedidos%2FDiscuss%C3%A3o+de+bloqueio%2FMassmessage&subject=Discuss%C3%A3o+de+bloqueio+de+".urlencode($conta)."&message=%7B%7Bsubst%3AUsu%C3%A1rio%3ATeles%2FMassMessage%2FDesbloqueio%7C".urlencode($conta)."%7C%7C%7E%7E%7E%7E%7E%7C%7D%7D")."', '_blank')\">Solicitar mensagens em massa</li>\n";
		}
		echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/User_talk:".urlencode($conta)."?action=edit&section=new&nosummary=1&preload=Predefini%C3%A7%C3%A3o:Notifica%C3%A7%C3%A3o_de_discuss%C3%A3o_de_bloqueio/Preload', '_blank')\">Enviar notificação ao usuário</li>\n";
	} else {
		$desambig = FALSE;
		foreach (current($result)['categories'] as $cat) {
			if ($cat['title'] == "Categoria:!Desambiguações de pedidos de discussão de bloqueio") $desambig = TRUE;
		}
		if (!$desambig) {
			//Criar página /2 + Mover raiz para /1 + Criar desambiguação
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?action=edit&preload=Template%3ADB1%2FPreload&title=Wikipedia%3APedidos+a+administradores%2FDiscuss%C3%A3o+de+bloqueio%2F".urlencode($conta)."%2F2&preloadparams%5B%5D=".urlencode($evidence)."&preloadparams%5B%5D=".urlencode("{{subst:#if:".$defesa."|{{citação2|1=".$defesa."|2={{subst:#if:".$diff."|{{dif|".$diff."}}}}}}}}")."', '_blank')\">Criar DB/2</li>\n";
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/Lista_de_pedidos?action=edit&section=new&nosummary=1&preload=Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/Lista_de_pedidos/Preload&preloadparams%5B%5D=".urlencode($conta)."/2&preloadparams%5B%5D=".urlencode($conta)."', '_blank')\">Publicar DB na lista de pedidos</li>\n";
			echo "<li class=\"w3-padding-small w3-left-align\"><textarea readonly rows='1' cols='2' style='resize: none;margin-bottom: -8px;' id='myInput2'>".str_replace("|BloqueioConcluídosTotal", "* [[Wikipédia:Pedidos a administradores/Discussão de bloqueio/".$conta."/2|".$conta."]]\n|BloqueioConcluídosTotal", preg_replace('/BloqueioAbertosTotal=(\d)/', 'BloqueioAbertosTotal={{subst:#expr:$1+1}}', file_get_contents("https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:MRConduta&action=raw")))."</textarea><button onclick='copyclip2()'>Copiar código do painel</button></li><script>function copyclip2(){var e=document.getElementById('myInput2');e.select(),e.setSelectionRange(0,99999),document.execCommand('copy')}</script>";
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:MRConduta&action=edit', '_blank')\">Colar (substituir) novo código no painel</li>";
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Especial:Mover_p%C3%A1gina/Wikipedia%3APedidos_a_administradores%2FDiscuss%C3%A3o_de_bloqueio%2F".urlencode($conta)."?wpReason=Arquivando+discussão+anterior&wpLeaveRedirect=1&wpNewTitle=Pedidos+a+administradores%2FDiscuss%C3%A3o+de+bloqueio%2F".urlencode($conta)."%2F2', '_blank')\">Mover DB anterior para /1</li>\n";
			echo "<li class=\"w3-padding-small w3-left-align\"><textarea readonly rows='1' cols='2' style='resize: none;margin-bottom: -8px;' id='myInput'>{{!Desambiguação}}\n\n*[[/1|1.º pedido]]\n*[[/2|2.º pedido]]\n\n[[Categoria:!Desambiguações de ".urlencode($conta)."]]\n[[Categoria:!Desambiguações de pedidos de discussão de bloqueio|".urlencode($conta)."]]</textarea><button onclick='copyclip()'>Copiar código de desambiguação</button></li><script>function copyclip(){var e=document.getElementById('myInput');e.select(),e.setSelectionRange(0,99999),document.execCommand('copy')}</script>";
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/".urlencode($conta)."&action=edit', '_blank')\">Criar desambiguação</li>";
			if ($sysop) {
				echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Especial:Mensagens_em_massa&spamlist=Wikip%C3%A9dia%3APedidos%2FDiscuss%C3%A3o+de+bloqueio%2FMassmessage&subject=Discuss%C3%A3o+de+bloqueio+de+".urlencode($conta)."&message=%7B%7Bsubst%3AUsu%C3%A1rio%3ATeles%2FMassMessage%2FDesbloqueio%7C".urlencode($conta)."%7C%7C~~~~~%7C2%7D%7D', '_blank')\">Enviar mensagens em massa</li>";	
			} else {
				echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Wikip%C3%A9dia:Pedidos/Outros?action=edit&section=new&preloadtitle=Mensagens+em+massa+para+discussão+de+bloqueio+do+usuário+".urlencode($conta)."&preload=Wikip%C3%A9dia:Pedidos/Outros/PreloadMassMessageDB&preloadparams%5B%5D=".urlencode($conta)."&preloadparams%5B%5D=".urlencode("https://pt.wikipedia.org/w/index.php?title=Especial:Mensagens_em_massa&spamlist=Wikip%C3%A9dia%3APedidos%2FDiscuss%C3%A3o+de+bloqueio%2FMassmessage&subject=Discuss%C3%A3o+de+bloqueio+de+".urlencode($conta)."&message=%7B%7Bsubst%3AUsu%C3%A1rio%3ATeles%2FMassMessage%2FDesbloqueio%7C".urlencode($conta)."%7C%7C%7E%7E%7E%7E%7E%7C2%7D%7D")."', '_blank')\">Solicitar mensagens em massa</li>\n";
			}
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/User_talk:".urlencode($conta)."?action=edit&section=new&nosummary=1&preload=Predefini%C3%A7%C3%A3o:Notifica%C3%A7%C3%A3o_de_discuss%C3%A3o_de_bloqueio/Preload', '_blank')\">Enviar notificação ao usuário</li>\n";
		} else {
			$params = [
			    "action" => "query",
			    "list" => "prefixsearch",
			    "pslimit" => "max",
			    "pssearch" => "Wikipédia:Pedidos a administradores/Discussão de bloqueio/".$conta."/",
			    "format" => "json"
			];

			$ch = curl_init( $endPoint . "?" . http_build_query( $params ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$output = curl_exec( $ch );
			curl_close( $ch );

			$count = count(json_decode( $output, true )["query"]["prefixsearch"])+1;
			
			//Criar página /$count
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?action=edit&preload=Template%3ADB1%2FPreload&title=Wikipedia%3APedidos+a+administradores%2FDiscuss%C3%A3o+de+bloqueio%2F".urlencode($conta)."%2F".$count."&preloadparams%5B%5D=".urlencode($evidence)."&preloadparams%5B%5D=".urlencode("{{subst:#if:".$defesa."|{{citação2|1=".$defesa."|2={{subst:#if:".$diff."|{{dif|".$diff."}}}}}}}}")."', '_blank')\">Criar DB</li>\n";
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/Lista_de_pedidos?action=edit&section=new&nosummary=1&preload=Wikip%C3%A9dia:Pedidos_a_administradores/Discuss%C3%A3o_de_bloqueio/Lista_de_pedidos/Preload&preloadparams%5B%5D=".urlencode($conta)."%2F".$count."&preloadparams%5B%5D=".urlencode($conta)."', '_blank')\">Publicar DB na lista de pedidos</li>\n";
			echo "<li class=\"w3-padding-small w3-left-align\"><textarea readonly rows='1' cols='2' style='resize: none;margin-bottom: -8px;' id='myInput2'>".str_replace("|BloqueioConcluídosTotal", "* [[Wikipédia:Pedidos a administradores/Discussão de bloqueio/".$conta."/".$count."|".$conta."]]\n|BloqueioConcluídosTotal", preg_replace('/BloqueioAbertosTotal=(\d)/', 'BloqueioAbertosTotal={{subst:#expr:$1+1}}', file_get_contents("https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:MRConduta&action=raw")))."</textarea><button onclick='copyclip2()'>Copiar código do painel</button></li><script>function copyclip2(){var e=document.getElementById('myInput2');e.select(),e.setSelectionRange(0,99999),document.execCommand('copy')}</script>";
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:MRConduta&action=edit', '_blank')\">Colar (substituir) novo código no painel</li>";
			if ($sysop) {
				echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/w/index.php?title=Especial:Mensagens_em_massa&spamlist=Wikip%C3%A9dia%3APedidos%2FDiscuss%C3%A3o+de+bloqueio%2FMassmessage&subject=Discuss%C3%A3o+de+bloqueio+de+".urlencode($conta)."&message=%7B%7Bsubst%3AUsu%C3%A1rio%3ATeles%2FMassMessage%2FDesbloqueio%7C".urlencode($conta)."%7C%7C~~~~~%7C".$count."%7D%7D', '_blank')\">Enviar mensagens em massa</li>";
			} else {
				echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/Wikip%C3%A9dia:Pedidos/Outros?action=edit&section=new&preloadtitle=Mensagens+em+massa+para+discussão+de+bloqueio+do+usuário+".urlencode($conta)."&preload=Wikip%C3%A9dia:Pedidos/Outros/PreloadMassMessageDB&preloadparams%5B%5D=".urlencode($conta)."&preloadparams%5B%5D=".urlencode("https://pt.wikipedia.org/w/index.php?title=Especial:Mensagens_em_massa&spamlist=Wikip%C3%A9dia%3APedidos%2FDiscuss%C3%A3o+de+bloqueio%2FMassmessage&subject=Discuss%C3%A3o+de+bloqueio+de+".urlencode($conta)."&message=%7B%7Bsubst%3AUsu%C3%A1rio%3ATeles%2FMassMessage%2FDesbloqueio%7C".urlencode($conta)."%7C%7C%7E%7E%7E%7E%7E%7C".$count."%7D%7D")."', '_blank')\">Solicitar mensagens em massa</li>\n";
			}
			echo "<li class=\"w3-padding-small w3-left-align\" style=\"cursor:pointer;\" onclick=\"window.open('https://pt.wikipedia.org/wiki/User_talk:".urlencode($conta)."?action=edit&section=new&nosummary=1&preload=Predefini%C3%A7%C3%A3o:Notifica%C3%A7%C3%A3o_de_discuss%C3%A3o_de_bloqueio/Preload', '_blank')\">Enviar notificação ao usuário</li>\n";
		}
	}
} else echo "Preencha o formulário ao lado";
						?></ul></div>
		      		</div>
		      	</div>
      		</div>
      	</div>
		<hr>
		<a href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge-button.png" alt="Powered by Wikimedia Toolforge"></a>
	</body>
</html>