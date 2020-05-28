<?php

declare(strict_types=1);

namespace App\Infrastructure\MediaWiki;

use App\Application\ParserInterface;
use App\Domain\ReportedCases;
use App\Domain\States;
use Carbon\CarbonImmutable as DateTimeImmutable;
use Carbon\CarbonInterface as DateTimeInterface;
use Carbon\CarbonInterval as DateInterval;
use Carbon\CarbonPeriod as DatePeriod;

final class EnglishTable implements ParserInterface
{
    private const VALUE_FORMAT = '{{#ifeq: {{{show|total}}} | new | %s | %s }}';

    public function parse($reportedCases): string
    {
        $contents = $this->buildHeader();

        $nationalCases = $reportedCases->nationalCases();
        $stateCases = $reportedCases->stateCases();

        foreach ($this->getDateRange($reportedCases) as $date) {
            $contents .= $this->buildRow($date, $nationalCases, $stateCases);
        }

        $contents .= "\n" . $this->buildFooter();

        return $contents;
    }

    private function buildRow(DateTimeInterface $date, ReportedCases $nationalCases, ReportedCases $stateCases): string
    {
        // filters data
        $currentCases = $nationalCases->filterByDate($date);

        if (0 === $currentCases->getTotalCumulativeCases()) {
            return '';
        }

        $previousCases = $nationalCases->filterByDate($date->sub(1, 'day'));

        $states = States::english()->sort();

        $currentStateCases = $stateCases->filterByDate($date);
        $previousStateCases = $stateCases->filterByDate($date->sub(1, 'day'));

        // calculates data
        $totalCumulativeCases = $currentCases->getTotalCumulativeCases();
        $totalNewCases = $totalCumulativeCases - $previousCases->getTotalCumulativeCases();

        $totalCumulativeDeaths = $currentCases->getTotalCumulativeDeaths();
        $totalNewDeaths = $totalCumulativeDeaths - $previousCases->getTotalCumulativeDeaths();

        $casesByState = $this->listStateData($currentStateCases, $previousStateCases, 'getTotalCumulativeCases');
        $deathsByState = $this->listStateData($currentStateCases, $previousStateCases, 'getTotalCumulativeDeaths');

        // parses data to textual mode
        $totalNewCases = $totalCumulativeCases ? ($totalNewCases ? '+' . ($totalNewCases) : '=') : '';
        $totalCumulativeCases = $totalCumulativeCases ?: '';

        $totalNewDeaths = $totalCumulativeDeaths ? ($totalNewDeaths ? '+' . ($totalNewDeaths) : '=') : '';
        $totalCumulativeDeaths = $totalCumulativeDeaths ?: '';

        $casesByState = preg_replace('/  /', ' ', implode(' || ', $casesByState));
        $deathsByState = preg_replace('/  /', ' ', implode(' || ', $deathsByState));

        // parses the row
        return <<<ROW
|-
!rowspan=2 style='vertical-align:top'| {$date->format('M j')}
! Cases
| {$casesByState}
!rowspan=2| {$totalNewCases}
!rowspan=2| {$totalCumulativeCases}
|rowspan=2| {$totalNewDeaths}
|rowspan=2| {$totalCumulativeDeaths}
|-
! Deaths
| {$deathsByState}

ROW;
    }

    private function listStateData(ReportedCases $currentCases, ReportedCases $previousCases, string $method): array
    {
        $states = States::english()->sort();
        $data = [];

        foreach ($states->getArrayCopy() as $state) {
            $cumulative = $currentCases->filterByState($state)->{$method}();

            if (!$cumulative) {
                $data[] = '';

                continue;
            }

            $new = $cumulative - $previousCases->filterByState($state)->{$method}();

            $data[] = sprintf(static::VALUE_FORMAT, $new ?: '', $cumulative ?: '');
        }

        return $data;
    }

    private function buildHeader()
    {
        $states = $this->stateColumns();

        return <<<HEADER
{| class="wikitable mw-datatable mw-collapsible" style="font-size:80%; text-align: center;"
|+ style="font-size:125%" |{{nowrap|COVID-19 cases and deaths in Brazil, by state({{navbar|COVID-19 pandemic data/Brazil medical cases|mini=1|nodiv=1}})}}
!rowspan=2 colspan=2|
!colspan=7| [[North_Region,_Brazil|North]]
!colspan=9| [[Northeast_Region,_Brazil|Northeast]]
!colspan=4| [[Central-West_Region,_Brazil|Central-West]]
!colspan=4| [[Southeast_Region,_Brazil|Southeast]]
!colspan=3| [[South_Region,_Brazil|South]]
!colspan=2| Cases
!colspan=2| Deaths
|-
{$states}
! New
! Total
! New
! Total

HEADER;
    }

    private function buildFooter()
    {
        $states = $this->stateColumns();

        return <<<FOOTER
|-
|-
!rowspan=2 colspan=2|
{$states}
! New
! Total
! New
! Total
|-
!colspan=7| [[North_Region,_Brazil|North]]
!colspan=9| [[Northeast_Region,_Brazil|Northeast]]
!colspan=4| [[Central-West_Region,_Brazil|Central-West]]
!colspan=4| [[Southeast_Region,_Brazil|Southeast]]
!colspan=3| [[South_Region,_Brazil|South]]
!colspan=2| Cases
!colspan=2| Deaths
|-
| colspan="33" |
|-
| colspan="33" style="text-align: left;" | Notes:<br/>
{{note|1}} Official data provided by the Brazilian Ministry of Health<ref name=brazil>{{cite web|url=https://covid.saude.gov.br/|title=Ministério da Saúde|date=April 2020}}</ref>
|-
|}<noinclude>{{doc}}</noinclude>

FOOTER;
    }

    private function stateColumns(): string
    {
        $contents = [];

        $format = '! {{flagicon|%s}} <br/> [[%s|%s]]';

        $states = States::english()->sort();
        foreach ($states->getArrayCopy() as $state) {
            $contents[] = sprintf($format, $state->wikipediaFlag(), $state->wikipediaEntry(), $state->code());
        }

        return implode("\n", $contents);
    }

    private function getDateRange(ReportedCases $reportedCases): array
    {
        return DatePeriod::create()
            ->every(DateInterval::make(1, 'day'))
            ->setDates($reportedCases->getFirstReportedDate(), $reportedCases->getLastReportedDate())
            ->setDateClass(DateTimeImmutable::class)
            ->toArray();
    }
}
