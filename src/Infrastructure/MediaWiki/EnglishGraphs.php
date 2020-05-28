<?php

declare(strict_types=1);

namespace App\Infrastructure\MediaWiki;

use App\Application\ParserInterface;
use App\Domain\City;
use App\Domain\Region;
use App\Domain\ReportedCases;
use Carbon\CarbonImmutable as DateTimeImmutable;
use Carbon\CarbonInterface as DateTimeInterface;
use Carbon\CarbonInterval as DateInterval;
use Carbon\CarbonPeriod as DatePeriod;

final class EnglishGraphs implements ParserInterface
{
    public function parse($cases): string
    {
        $contents = $this->buildHeader();

        $contents .= "\n" . $this->buildStatisticalGraphs($cases);
        $contents .= "\n" . $this->buildHistoricalTable($cases);

        $contents .= "\n" . $this->buildFooter();

        return $contents;
    }

    private function buildStatisticalGraphs(ReportedCases $cases): string
    {
        $contents = '== Statistics ==';
        $contents .= "\n<section begin=\"Statistics\"/>";
        //$contents .= "\nDue the high volume of data, the graphs display data once every 3 days.";

        $contents .= "\n<div style='display: inline-block; width: 800px; vertical-align: top;'>";
        $contents .= "\n" . $this->buildTotalConfirmedCasesGraph($cases);
        $contents .= "\n" . $this->buildNewConfirmedCasesGraph($cases);
        $contents .= "\n<noinclude>";
        $contents .= "\n" . $this->buildTotalConfirmedCasesByRegionGraph($cases);
        $contents .= "\n" . $this->buildGrowthOfConfirmedCases($cases);
        //$contents .= "\n" . $this->buildTotalConfirmedCasesByCapital($cases);
        $contents .= "\n</noinclude>";
        $contents .= "\n</div>";

        $contents .= "\n<div style='display: inline-block; width: 800px; vertical-align: top;'>";
        $contents .= "\n" . $this->buildTotalConfirmedDeathsGraphs($cases);
        $contents .= "\n" . $this->buildNewConfirmedDeathsGraphs($cases);
        $contents .= "\n<noinclude>";
        $contents .= "\n" . $this->buildTotalConfirmedDeathsByRegionGraph($cases);
        $contents .= "\n" . $this->buildGrowthOfConfirmedDeaths($cases);
        $contents .= "\n</noinclude>";
        $contents .= "\n</div>";

        $contents .= "\n<noinclude>";
        $contents .= "\n" . $this->buildLogChart();
        $contents .= "\n</noinclude>";

        $contents .= "\n<section end=\"Statistics\"/>";

        return $contents;
    }

    private function buildTotalConfirmedCasesGraph(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases));
        $data = implode(', ', $this->listTotalCumulativeCases($cases->nationalCases()));

        return <<<GRAPH
=== Total confirmed cases ===
{{Graph:Chart
|type=line
|linewidth=2
|showSymbols=1
|width=700
|colors={{Medical cases chart/Bar colors|3}}
|showValues=
|xAxisTitle=Date
|xType=date
|xAxisFormat=%b %e
|x={$dates}
|y1Title=Total confirmed cases
|yAxisTitle=Total confirmed cases
|y1={$data}
|yGrid= |xGrid=
}}

GRAPH;
    }

    private function buildNewConfirmedCasesGraph(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases, 'M j'));
        $data = implode(', ', $this->listTotalNewCases($cases->nationalCases()));

        return <<<GRAPH
=== New cases, per day ===
<div style="max-width: 800px; overflow-x: scroll;">
{{Graph:Chart
|type=rect
|linewidth=1
|showSymbols=1
|width=1400
|colors={{Medical cases chart/Bar colors|3}}
|showValues=offset:2
|xAxisAngle=-60
|xAxisTitle=Date
|x={$dates}
|y1Title=New cases
|yAxisTitle=New cases
|y1={$data}
|yGrid=
}}
</div>

GRAPH;
    }

    private function buildTotalConfirmedCasesByRegionGraph(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases));

        $cases = $cases->stateCases();

        $northData = implode(', ', $this->listTotalCumulativeCases($cases->filterByRegion(Region::north())));
        $northeastData = implode(', ', $this->listTotalCumulativeCases($cases->filterByRegion(Region::northeast())));
        $centralwestData = implode(', ', $this->listTotalCumulativeCases($cases->filterByRegion(Region::centralWest())));
        $southeastData = implode(', ', $this->listTotalCumulativeCases($cases->filterByRegion(Region::southeast())));
        $southData = implode(', ', $this->listTotalCumulativeCases($cases->filterByRegion(Region::south())));

        return <<<GRAPH
=== Total confirmed cases, by region ===
{{Graph:Chart
|type=line
|linewidth=2
|showSymbols=1
|width=600
|colors=#00FF00, #0000FF, #FFFF00, #FF0000, #00FFFF
|showValues=
|legend=
|xAxisTitle=Date
|xType=date
|xAxisFormat=%b %e
|x={$dates}
|yAxisTitle=Total confirmed cases
|y1Title=North
|y1={$northData}
|y2Title=Northeast
|y2={$northeastData}
|y3Title=Central-West
|y3={$centralwestData}
|y4Title=Southeast
|y4={$southeastData}
|y5Title=South
|y5={$southData}
|yGrid= |xGrid=
}}

GRAPH;
    }

    private function buildGrowthOfConfirmedCases(ReportedCases $cases): string
    {
        $totalConfirmedCases = $this->listTotalCumulativeCases($cases->nationalCases());
        $newCases = $this->listTotalNewCases($cases->nationalCases());

        foreach ($newCases as $key => $value) {
            if (0 === $value) {
                unset($newCases[$key], $totalConfirmedCases[$key]);
            }
        }

        $totalConfirmedCases = implode(', ', $totalConfirmedCases);
        $newCases = implode(', ', $newCases);

        return <<<GRAPH
=== Growth of confirmed cases ===
{{Side box
|position=Left
|metadata=No
|above='''Growth of confirmed cases'''<br/><small>a rising straight line indicates exponential growth, while a horizontal line indicates linear growth</small>
|abovestyle=text-align:center
|below=<small>Source: Brazilian Ministry of Health</small>
|text= {{Graph:Chart
    |type=line
    |linewidth=2
    |width=600
    |colors={{Medical cases chart/Bar colors|3}}
    |showValues=
    |xAxisTitle=Total confirmed cases
    |xAxisAngle=-30
    |xScaleType=log
    |x={$totalConfirmedCases}
    |yAxisTitle=New confirmed cases
    |yScaleType=log
    |y={$newCases}
    |yGrid= |xGrid=
    }}
}}

GRAPH;
    }

    private function buildTotalConfirmedCasesByCapital(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases));

        $cases = $cases->localCases();

        $belem = implode(', ', $this->listTotalCumulativeCases($cases->filterByCity(City::fromCode(City::BELEM))));
        $fortaleza = implode(', ', $this->listTotalCumulativeCases($cases->filterByCity(City::fromCode(City::FORTALEZA))));
        $manaus = implode(', ', $this->listTotalCumulativeCases($cases->filterByCity(City::fromCode(City::MANAUS))));
        $recife = implode(', ', $this->listTotalCumulativeCases($cases->filterByCity(City::fromCode(City::RECIFE))));
        $rioDeJaneiro = implode(', ', $this->listTotalCumulativeCases($cases->filterByCity(City::fromCode(City::RIO_DE_JANEIRO))));
        $saoPaulo = implode(', ', $this->listTotalCumulativeCases($cases->filterByCity(City::fromCode(City::SAO_PAULO))));

        return <<<GRAPH
== Total confirmed cases, by Capital ==
{{Side box
|position=Left
|metadata=No
|above='''Total confirmed cases, by Capital'''
|abovestyle=text-align:center
|below=<small>Source: Brazilian Ministry of Health</small>
|text= {{Graph:Chart
    |type=line
    |linewidth=2
    |showSymbols=1
    |width=600
    |colors=#00FF00, #0000FF, #FFFF00, #FF0000, #00FFFF
    |showValues=
    |legend=
    |xAxisTitle=Date
    |xType=date
    |xAxisFormat=%b %e
    |x={$dates}
    |yAxisTitle=Total confirmed cases
    |y1Title=Belém
    |y1={$belem}
    |y2Title=Fortaleza
    |y2={$fortaleza}
    |y3Title=Manaus
    |y3={$manaus}
    |y4Title=Recife
    |y4={$recife}
    |y5Title=Rio de Janeiro
    |y5={$rioDeJaneiro}
    |y6Title=São Paulo
    |y6={$saoPaulo}
    |yGrid= |xGrid=
    }}
}}

GRAPH;
    }

    private function buildTotalConfirmedDeathsGraphs(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases));
        $data = implode(', ', $this->listTotalCumulativeDeaths($cases->nationalCases()));

        return <<<GRAPH
=== Total confirmed deaths ===
{{Graph:Chart
|type=line
|linewidth=2
|showSymbols=1
|width=700
|colors={{Medical cases chart/Bar colors|1}}
|showValues=
|xAxisTitle=Date
|xType=date
|xAxisFormat=%b %e
|x={$dates}
|y1Title=Total confirmed deaths
|yAxisTitle=Total confirmed deaths
|y1={$data}
|yGrid= |xGrid=
}}

GRAPH;
    }

    private function buildNewConfirmedDeathsGraphs(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases, 'M j'));
        $data = implode(', ', $this->listTotalNewDeaths($cases->nationalCases()));

        return <<<GRAPH
=== New deaths, per day ===
<div style="max-width: 800px; overflow-x: scroll;">
{{Graph:Chart
|type=rect
|linewidth=1
|showSymbols=1
|width=1400
|colors={{Medical cases chart/Bar colors|1}}
|showValues=offset:2
|xAxisAngle=-60
|xAxisTitle=Date
|x={$dates}
|y1Title=New deaths
|yAxisTitle=New deaths
|y1={$data}
|yGrid=
}}
</div>

GRAPH;
    }

    private function buildTotalConfirmedDeathsByRegionGraph(ReportedCases $cases): string
    {
        $dates = implode(', ', $this->listDates($cases));

        $cases = $cases->stateCases();

        $northData = implode(', ', $this->listTotalCumulativeDeaths($cases->filterByRegion(Region::north())));
        $northeastData = implode(', ', $this->listTotalCumulativeDeaths($cases->filterByRegion(Region::northeast())));
        $centralwestData = implode(', ', $this->listTotalCumulativeDeaths($cases->filterByRegion(Region::centralWest())));
        $southeastData = implode(', ', $this->listTotalCumulativeDeaths($cases->filterByRegion(Region::southeast())));
        $southData = implode(', ', $this->listTotalCumulativeDeaths($cases->filterByRegion(Region::south())));

        return <<<GRAPH
=== Total confirmed deaths, by region ===
{{Graph:Chart
|type=line
|linewidth=2
|showSymbols=1
|width=600
|colors=#00FF00, #0000FF, #FFFF00, #FF0000, #00FFFF
|showValues=
|legend=
|xAxisTitle=Date
|xType=date
|xAxisFormat=%b %e
|x={$dates}
|yAxisTitle=Total confirmed deaths
|y1Title=North
|y1={$northData}
|y2Title=Northeast
|y2={$northeastData}
|y3Title=Central-West
|y3={$centralwestData}
|y4Title=Southeast
|y4={$southeastData}
|y5Title=South
|y5={$southData}
|yGrid= |xGrid=
}}

GRAPH;
    }

    private function buildGrowthOfConfirmedDeaths(ReportedCases $cases): string
    {
        $totalConfirmedDeaths = $this->listTotalCumulativeDeaths($cases->nationalCases());
        $newDeaths = $this->listTotalNewDeaths($cases->nationalCases());

        foreach ($newDeaths as $key => $value) {
            if (0 === $value) {
                unset($newDeaths[$key], $totalConfirmedDeaths[$key]);
            }
        }

        $totalConfirmedDeaths = implode(', ', $totalConfirmedDeaths);
        $newDeaths = implode(', ', $newDeaths);

        return <<<GRAPH
=== Growth of confirmed deaths ===
{{Side box
|position=Left
|metadata=No
|above='''Growth of confirmed deaths'''<br/><small>a rising straight line indicates exponential growth, while a horizontal line indicates linear growth</small>
|abovestyle=text-align:center
|below=<small>Source: Brazilian Ministry of Health</small>
|text= {{Graph:Chart
    |type=line
    |linewidth=2
    |width=600
    |colors={{Medical cases chart/Bar colors|1}}
    |showValues=
    |xAxisTitle=Total confirmed deaths
    |xAxisAngle=-30
    |xScaleType=log
    |x={$totalConfirmedDeaths}
    |yAxisTitle=New confirmed deaths
    |yScaleType=log
    |y={$newDeaths}
    |yGrid= |xGrid=
    }}
}}

GRAPH;
    }

    private function buildLogChart(): string
    {
        return <<<'CHART'
=== Number of cases and deaths, on a logarithmic scale ===
[[File:CoViD-19 BR.svg|thumb|600px|upright=2|left|Number of cases (blue) and number of deaths (red) on a [[logarithmic scale]].]]
{{clear}}

CHART;
    }

    private function buildHistoricalTable(ReportedCases $cases): string
    {
        return <<<'TABLE'
== Timeline table, by state ==
<section begin="Timeline"/>
=== Total cases and deaths, by state ===
{{COVID-19 pandemic data/Brazil_medical_cases}}

=== New cases and deaths, by state ===
{{COVID-19 pandemic data/Brazil medical cases|show=new}}

<section end="Timeline"/>

TABLE;
    }

    private function listTotalCumulativeCases(ReportedCases $reportedCases): array
    {
        $data = [];

        foreach ($this->getDateRange($reportedCases) as $day) {
            $data[] = $reportedCases->filterByDate($day)->getTotalCumulativeCases();
        }

        return $data;
    }

    private function listTotalNewCases(ReportedCases $reportedCases): array
    {
        $data = [];

        foreach ($this->getDateRange($reportedCases) as $day) {
            $previousDay = $day->sub(1, 'day');

            $totalNewCases = $reportedCases->filterByDate($day)->getTotalCumulativeCases();
            $totalNewCases -= $reportedCases->filterByDate($previousDay)->getTotalCumulativeCases();

            $data[] = $totalNewCases;
        }

        return $data;
    }

    private function listTotalCumulativeDeaths(ReportedCases $reportedCases): array
    {
        $data = [];

        foreach ($this->getDateRange($reportedCases) as $day) {
            $data[] = $reportedCases->filterByDate($day)->getTotalCumulativeDeaths();
        }

        return $data;
    }

    private function listTotalNewDeaths(ReportedCases $reportedCases): array
    {
        $data = [];

        foreach ($this->getDateRange($reportedCases) as $day) {
            $previousDay = $day->sub(1, 'day');

            $totalNewDeaths = $reportedCases->filterByDate($day)->getTotalCumulativeDeaths();
            $totalNewDeaths -= $reportedCases->filterByDate($previousDay)->getTotalCumulativeDeaths();

            $data[] = $totalNewDeaths;
        }

        return $data;
    }

    private function listDates(ReportedCases $reportedCases, string $format = 'Y-m-d'): array
    {
        return array_map(function (DateTimeInterface $day) use ($format) {
            return $day->format($format);
        }, $this->getDateRange($reportedCases));
    }

    private function getDateRange(ReportedCases $reportedCases): array
    {
        $end = $reportedCases->getLastReportedDate();

        return DatePeriod::create()
            ->every(DateInterval::make(1, 'day'))
            ->setDates('2020-02-26', $end->format('Y-m-d'))
            ->setDateClass(DateTimeImmutable::class)
            ->toArray();
    }

    private function buildHeader(): string
    {
        return <<<'HEADER'
{{main|COVID-19 pandemic in Brazil}}

HEADER;
    }

    private function buildFooter(): string
    {
        return <<<'FOOTER'
== References ==
{{reflist|colwidth=30em}}

== External links ==
* https://covid.saude.gov.br/ – Ministry of Health Statistics Panel, updated daily

{{2019-nCoV|state=expanded}}
[[Category:COVID-19 pandemic in Brazil|statistics]]

FOOTER;
    }
}
