<?php

declare(strict_types=1);

namespace App\Infrastructure\MediaWiki;

use App\Application\ParserInterface;
use App\Domain\ReportedCases;
use Carbon\CarbonImmutable as DateTimeImmutable;
use Carbon\CarbonInterval as DateInterval;
use Carbon\CarbonPeriod as DatePeriod;

final class EnglishChart implements ParserInterface
{
    public function parse($reportedCases): string
    {
        $contents = $this->buildHeader($reportedCases);

        $previousTotalCases = 0;
        $currentTotalRepeats = 0;
        foreach ($this->getDateRange($reportedCases) as $date) {
            $currentDateCases = $reportedCases->filterByDate($date);
            $currentTotalCases = $currentDateCases->getTotalCumulativeCases();

            if ($previousTotalCases === $currentTotalCases) {
                if (0 === $currentTotalRepeats++) {
                    $contents .= "\n;;;{$currentTotalCases}";
                }

                continue;
            }
            $previousTotalCases = $currentTotalCases;
            $currentTotalRepeats = 0;

            $contents .= sprintf(
                "\n%s;%s;%s;%s",
                $date->format('Y-m-d'),
                $currentDateCases->getTotalCumulativeDeaths() ?: '',
                $currentDateCases->getTotalCumulativeRecoveries() ?: '',
                $currentDateCases->getTotalCumulativeCases() ?: ''
            );
        }
        $contents .= "\n" . $this->buildFooter();

        return $contents;
    }

    private function buildHeader(ReportedCases $reportedCases): string
    {
        $contents = <<<'HEADER'
{{Medical cases chart
|numwidth=wmwm
|disease=COVID-19
|location=Brazil
|outbreak=COVID-19 pandemic
|togglesbar=
<div class="nomobile" style="text-align:center">
HEADER;

        $months = [];
        foreach ($this->getDateRange($reportedCases) as $date) {
            $months[$date->format('m')] = $date->format('M');
        }
        ksort($months);

        $months = array_map(function (string $month) {
            return sprintf('{{Medical cases chart/Month toggle button|%s}}', mb_strtolower($month));
        }, $months);

        $contents .= "\n".implode("\n", $months);

        $contents .= "\n" . <<<'HEADER'
{{Medical cases chart/Month toggle button|l15}}
</div>
|right2     = # of deaths
|changetype2= p <!-- p = percent & a = absolute -->
|recoveries=y
|collapsible=y
|data=
HEADER;

        return $contents;
    }

    private function buildFooter(): string
    {
        return <<<'FOOTER'
|caption='''Sources:'''
* Brazilian Ministry of Health <ref>{{cite web|url=https://coronavirus.saude.gov.br/|title=Brazil Ministry of Health|date=May 2020}}</ref>
}}<noinclude>{{doc}}</noinclude>
FOOTER;
    }

    private function getDateRange(ReportedCases $reportedCases): array
    {
        $begin = new DateTimeImmutable('2020-02-26');
        $interval = new DateInterval('P1D');
        $end = $reportedCases->getLastReportedDate();
        $end = $end->add($interval);

        $period = new DatePeriod($begin, $interval, $end);

        $dates = [];
        foreach ($period as $dates[]);

        return $dates;
    }
}
