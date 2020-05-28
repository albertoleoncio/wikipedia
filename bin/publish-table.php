<?php

defined('ROOT_DIR') or define('ROOT_DIR', realpath(__DIR__ . '/..'));

require_once ROOT_DIR . '/vendor/autoload.php';

\Dotenv\Dotenv::createImmutable(ROOT_DIR)->load();

$wikiArticle = 'Predefinição:Casos de COVID-19 no Brasil';

$outputDir = ROOT_DIR . "/var/output/wikipedia-pt";
$tableFile = $outputDir . '/table.txt';

if (!file_exists($tableFile)) {
    echo sprintf("File not found: %s\n", $tableFile);
    return 1;
}

$updatedAt = new \DateTime(date('Y-m-d H:00:00', filemtime($tableFile)));
if ($updatedAt->format('U') < strtotime('-3 hours')) {
    echo "File is older than 3 hours. Will not publish.\n";
    return 0;
}

echo sprintf("Stablishing connetion to wikipedia api\n");
$wiki = new Wikimate($_ENV['WIKIMATE_API_URL']);
$wiki->login($_ENV['WIKIMATE_USERNAME'], $_ENV['WIKIMATE_PASSWORD']);

if ($error = $wiki->getError()) {
    echo sprintf("Unable to login to pt.wikipedia.org: %s\n", $error['login']);
    return 1;
}

echo sprintf("Reading contents from file '%s'\n", $tableFile);
$contents = file_get_contents($tableFile);

//Recupera dados da predefinição
echo sprintf("Check if wiki article exists '%s'\n", $wikiArticle);
$page = $wiki->getPage($wikiArticle);

if (!$page->exists()) {
    echo "Wiki article not found!\n";
    return 1;
}

echo "Updating the wiki article\n";
$page->setText($wikiCode, 0, true, "bot: Atualizando estatísticas");

if ($error = $page->getError()) {
    echo sprintf("Error updating the wiki article: %s\n", print_r($error, true));
    return 1;
}

echo "Wiki article updated!\n";
return 0;
