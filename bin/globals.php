<?php
// Configurações para exibir erros durante a execução do código
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuração da localização e fuso horário para o Brasil (Bahia)
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Bahia');

// Importação do arquivo de credenciais para utilizar informações privadas
require_once __DIR__.'/../../credenciais.php';

// URL da API da Wikipedia em português (padrão)
$api_url = 'https://pt.wikipedia.org/w/api.php';

// Função para substituir strftime() por IntlDateFormatter, deprecada em PHP 8.1, para formatar datas em português
function formatPortugueseDate(string $pattern, ?int $timestamp = null): string
{
    $formatter = new IntlDateFormatter(
        'pt_BR',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        date_default_timezone_get(),
        IntlDateFormatter::GREGORIAN,
        $pattern
    );
    $formattedDate = $formatter->format($timestamp ?? time());

    if ($formattedDate === false) {
        throw new RuntimeException('Não foi possível formatar a data em português.');
    }

    return $formattedDate;
}
