<?php

declare(strict_types=1);

namespace App\Infrastructure\Svg;

use App\Application\ParserInterface;
use Carbon\Carbon as DateTime;

final class RioGrandeDoSulMapParser implements ParserInterface
{
    private $templateName;

    public function __construct(string $templateName)
    {
        $this->templateName = $templateName;
    }

    public function parse($cases): string
    {
        $currentData = $cases->filterByDate(new DateTime('yesterday'));

        $contents = "microRegion, cases, deaths\n";

        $macroRegions = [
            'Centro Ocidental',
            'Centro Oriental',
            'Metropolitana',
            'Nordeste',
            'Noroeste',
            'Sudeste',
            'Sudoeste',
        ];

        $microRegions = [
            'Cachoeira do Sul',
            'Camaquã',
            'Campanha Central',
            'Campanha Meridional',
            'Campanha Ocidental',
            'Carazinho',
            'Caxias do Sul',
            'Cerro Largo',
            'Cruz Alta',
            'Erechim',
            'Frederico Westphalen',
            'Gramado-Canela',
            'Guaporé',
            'Ijuí',
            'Jaguarão',
            'Lajeado-Estrela',
            'Litoral Lagunar',
            'Montenegro',
            'Não-Me-Toque',
            'Osório',
            'Passo Fundo',
            'Pelotas',
            'Porto Alegre',
            'Restinga Seca',
            'Sananduva',
            'Santa Cruz do Sul',
            'Santa Maria',
            'Santa Rosa',
            'Santiago',
            'Santo Ângelo',
            'São Jerônimo',
            'Serras de Sudeste',
            'Soledade',
            'Três Passos',
            'Vacaria',
        ];

        foreach ($microRegions as $microRegion) {
            $filteredCases = $currentData->filterByMicroRegion($microRegion);
            $contents .= sprintf("%s, %s, %s\n", $microRegion, $filteredCases->getTotalConfirmedCases(), $filteredCases->getTotalConfirmedDeaths());
        }

        return $contents;
    }
}
