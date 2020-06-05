<?php
include 'globals.php';
echo "<pre>";

//Login
$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
	echo 'Login OK<hr>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
	die();
}

if (isset($_POST["nome"])) {

	$lista = explode("\n", $_POST["nome"]);

	foreach ($lista as $pagina) {
		echo $pagina."\n";
		$file = @$wiki->getFile(trim($pagina));
		if (!$file->exists()) {
			echo "File not found\n";
			break;
		}
		$history = $file->getHistory(true);

		if ($file->getHeight() > 500) {
			echo "Height > 500\n";
			break;
		}

		for ($i=1; $i < count($history); $i++) { 
			$archivename = $file->getArchivename($i);
			//$file->delete('bot: Solicitado em [[Special:diff/58435108|58435108]]', $archivename);
			echo $archivename." processado\n";
		}
		echo "<hr>";
	}
}
?>

Separar arquivo por cada linha
<br>
<form action="/alberobot/test.php" method="post">
  <textarea rows="6" cols="50" name="nome"></textarea>
  <input type="submit">
</form>
