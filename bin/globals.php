<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Bahia');
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../../credenciais.php';
$api_url = 'https://pt.wikipedia.org/w/api.php';