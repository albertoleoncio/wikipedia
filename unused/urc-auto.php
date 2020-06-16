<?php

$firstline = false;

if ($handle = fopen("lista3.txt",'c+')) {

    if (!flock($handle,LOCK_EX)) {
    	fclose($handle);
    }
    $offset = 0;
    $len = filesize($logFile);
    while (($line = fgets($handle)) !== false) {
        if (!$firstline) {
        	$firstline = $line;
        	$offset = strlen($firstline);
        	continue;
        }
        $pos = ftell($handle);
        fseek($handle,$pos-strlen($line)-$offset);
        fputs($handle,$line);
        fseek($handle,$pos);
    }
    fflush($handle);
    ftruncate($handle,($len-$offset));
    flock($handle,LOCK_UN);
    fclose($handle);
}

include 'globals.php';
echo "<pre>";

$wiki = new Wikimate($api_url);
if ($wiki->login('AlbeROBOT', $password))
	echo 'Login OK<hr>' ;
else {
	$error = $wiki->getError();
	echo "<b>Wikimate error</b>: ".$error['login'];
	die();
}

if (isset($firstline)) {

	$lista = explode("@", trim($firstline));

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
			$file->delete('Versão antiga de [[WP:URC|conteúdo restrito]] (bot:[[Special:diff/58435108|58435108]])', $archivename);
			echo $archivename." processado\n";
		}
		echo "<hr>";
	}
}