<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('UTC');
include 'globals.php';
include 'credenciais.php';
$today = strtotime('today');
$dados = array();
$wiki = new Wikimate('https://pt.wikipedia.org/w/api.php');
if ($wiki->login($username, $password))
	echo "OK" ;
else {
	$error = $wiki->getError();
	die("<b>Wikimate error</b>: ".$error['login']);
}
echo "<pre>";

//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	LISTA DE VARIÁVEIS																	//
//																						//
//	 $dados: array armazenadora das informações da proposição							//
//		[1]: texto da proposição														//
//		[2]: título do artigo-chave da proposição										//
//		[3]: nome de usuário do proponente 												//
//		[4]: texto da proposição para arquivamento										//
//		[5]: discussão da proposição para arquivamento									//
//																						//
//	$htmlA:	página de propostas 														//
//	$htmlB: predefinição da página principal 											//
//	$htmlC: página de discussão do artigo-chave 										//
//	$htmlD: página de discussão do usuário 												//
//	$htmlE: proposições recentes		 												//
//	$htmlF: arquivo de discussão da proposição											//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////



//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	A																					//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageA = $wiki->getPage("Wikipédia:Sabia que/Propostas");

//Recupera codigo-fonte da página
$htmlA = $pageA->getText();

//Explode código, dividindo por tópicos
$htmlAe = explode("\n==", $htmlA);

//Coleta proposição
preg_match_all('/\|texto = ([^\n]*)/', $htmlAe[1], $output1);
$dados[1] = ltrim($output1[1][0],"… ");

//Coleta artigo-chave da proposição
preg_match_all('/\'\'\'\[\[([^\]\|]*)/', $output1[1][0], $output2);
$dados[2] = $output2[1][0];

//Coleta nome de proponente
preg_match_all('/\* \'\'\'Proponente\'\'\' – [^\[]*\[\[[^:]*:([^|]*)/', $htmlAe[1], $output3);
$dados[3] = $output3[1][0];

//Coleta discussão da proposição e elimina proposição a publicar
$dados[5] = $htmlAe[1];
unset($htmlAe[1]);

//Remonta código da página
$htmlA = implode("\n==",$htmlAe);

//Grava página
/*if ($pageA->setText($htmlA, , true, "bot: Arquivando proposição publicada")) {
	echo "<hr>Arquivando proposição publicada\n";
} else {
	$error = $pageA->getError();
	echo "<hr>Error: ".print_r($error, true)."\n";
}*/



//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	B																					//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageB = $wiki->getPage("Predefinição:Sabia que");

//Recupera codigo-fonte da página
$htmlB = $pageB->getText();

//Explode código, dividindo por tópicos
$htmlBe = explode("\n…", $htmlB);

//Insere nova proposta com marcação de data, renumerando as demais
array_splice($htmlBe, 1, 0, " ".$dados[1]."<!--".strtolower(strftime('%B de %Y', $today))."-->\n");

//Explode último item da array, separando ultima proposição do rodapé da página
$ultima = explode("<!-- FIM", $htmlBe[count($htmlBe)-1]);

//Coleta texto da proposição para arquivamento
$dados[4] = ltrim($ultima[0]);

//Remonta rodapé da página
$htmlBe[count($htmlBe)-1] = "<!-- FIM".$ultima[1];

//Remonda último item da array
$htmlBe[count($htmlBe)-2] = $htmlBe[count($htmlBe)-2]."\n".$htmlBe[count($htmlBe)-1];

//Remove ultima proposição
array_pop($htmlBe);

//Remonta código da página
$htmlB = implode("\n…",$htmlBe);

//Grava página
/*if ($pageB->setText($htmlB, , true, "bot: Inserindo SabiaQueDiscussão")) {
	echo "<hr>Inserindo SabiaQueDiscussão\n";
} else {
	$error = $pageB->getError();
	echo "<hr>Error: ".print_r($error, true)."\n";
}*/



//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	C																					//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageC = $wiki->getPage("Discussão:".$dados[2]);

//Recupera dados da seção inicial da página de discussão do artigo-chave
$htmlC = $pageC->getSection(0);

//Verifica se a predefinição já existe. Se sim, insere nova predefinição no final da seção. Se não...
if (strpos($htmlC, "SabiaQueDiscussão") == false) {
	$htmlC = $htmlC."{{SabiaQueDiscussão\n|data1    = ".strftime('%d de %B de %Y', $today)."\n|entrada1 = … ".$dados[1]."\n}}";
} else {
	
}

//Grava página
/*if ($pageC->setText($htmlC, 0, true, "bot: Inserindo SabiaQueDiscussão")) {
	echo "<hr>Inserindo SabiaQueDiscussão\n";
} else {
	$error = $pageC->getError();
	echo "<hr>Error: ".print_r($error, true)."\n";
}*/



//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	D																					//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageD = $wiki->getPage("Usuário Discussão:".$dados[3]);

//Monta código da ParabénsSQ
$htmlD = "{{subst:ParabénsSQ|artigo=''[[".$dados[2]."]]''|data=".strftime('%d de %B de %Y', $today)."|curiosidade=…".$dados[1]."|arquivo=".strftime('%Y/%m', $today)."}} --~~~~";

//Grava página
/*if ($pageD->setText($htmlD, 'new', true, "bot: Inserindo ParabénsSQ")) {
	echo "<hr>Inserindo ParabénsSQ\n";
} else {
	$error = $pageD->getError();
	echo "<hr>Error: ".print_r($error, true)."\n";
}*/



//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	E																					//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageE = $wiki->getPage("Wikipédia:Sabia que/Arquivo/Recentes");

//Recupera seções da página
$sections = $pageE->getAllSections(false, WikiPage::SECTIONLIST_BY_NAME);

//Explode proposição para arquivar, separando-o da data de duplicação
$recente = explode("<!--", $dados[4]);

//Isola o nome do mês de publicação
$recente[1] = ucfirst(explode(' ',trim($recente[1]))[0]);

//Verifica se a seção com o nome do mês já existe. A partir disso, monta código da seção
if (array_key_exists($recente[1], $sections)) {
	$htmlE = "==== ".$recente[1]." ====\n*… ".$recente[0]."\n".trim($pageE->getSection($recente[1]));
	$section = 1;
} else {
	$htmlE = $pageE->getSection(0)."==== ".$recente[1]." ====\n*… ".$recente[0]."\n";
	$section = 0;
}

//Grava página
/*if ($pageE->setText($htmlE, $section, true, "bot: Inserindo Arquivo/Recentes")) {
	echo "<hr>Inserindo Arquivo/Recentes\n";
} else {
	$error = $pageE->getError();
	echo "<hr>Error: ".print_r($error, true)."\n";
}*/



//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	F																					//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//Define página
$pageF = $wiki->getPage("Wikipédia:Sabia que/Propostas/Arquivo/".strftime('%Y/%m', $today));

//Monta código da ParabénsSQ
$htmlF = "==".$dados[5]."{{ADC|sim|".strftime('%d de %B de %Y', $today)."|{{u|AlbeROBOT}}}}";

//Grava página
/*if ($pageF->setText($htmlF, 'new', true, "bot: Inserindo Propostas/Arquivo")) {
	echo "<hr>Inserindo Propostas/Arquivo\n";
} else {
	$error = $pageF->getError();
	echo "<hr>Error: ".print_r($error, true)."\n";
}*/
	


//////////////////////////////////////////////////////////////////////////////////////////
//																						//
//	Seção provisória, até a ativação da função de gravar página							//
//																						//
//////////////////////////////////////////////////////////////////////////////////////////

//print_r($dados);
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Wikip%C3%A9dia:Sabia_que/Propostas&action=edit">LINK</a>
	<textarea rows="4" cols="50">'.$htmlA.'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Predefini%C3%A7%C3%A3o:Sabia_que&action=edit">LINK</a>
	<textarea rows="4" cols="50">'.$htmlB.'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/wiki/Discuss%C3%A3o:'.$dados[2].'?action=edit&section=0">LINK</a>
	<textarea rows="4" cols="50">'.$htmlC.'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/wiki/Usu%C3%A1rio_Discuss%C3%A3o:'.$dados[3].'?action=edit&section=new">LINK</a>
	<textarea rows="4" cols="50">'.$htmlD.'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Wikipédia:Sabia que/Arquivo/Recentes&action=edit&section='.$section.'">LINK</a>
	<textarea rows="4" cols="50">'.$htmlE.'</textarea>';
echo '<hr><a href="https://pt.wikipedia.org/w/index.php?title=Wikipédia:Sabia que/Propostas/Arquivo/'.strftime('%Y/%m', $today).'&action=edit&section=new">LINK</a>
	<textarea rows="4" cols="50">'.$htmlF.'</textarea>';

?>