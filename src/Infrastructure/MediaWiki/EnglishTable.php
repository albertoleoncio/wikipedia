<?php

namespace App\Infrastructure\MediaWiki;

use App\Application\ParserInterface;
use App\Domain\ReportedCase;
use App\Domain\ReportedCases;

class EnglishTable implements ParserInterface
{
	public function parse(ReportedCases $cases): string
	{
		$contents = $this->buildHeader();

		foreach ($this->getDateInterval() as $day) {
			$contents .= $this->buildRow($day, $cases);
		}

		$contents .= $this->buildFooter();

		return $contents;
	}

	private function getDateInterval(): \DatePeriod
	{
		$begin = new \DateTime('2020-02-26');
		$end = new \DateTime('today');
		$end = $end->modify('+1 day');

		$interval = new \DateInterval('P1D');
		
		return new \DatePeriod($begin, $interval ,$end);
	}

	private function buildRow(\DateTime $day, ReportedCases $cases): string
	{
		if (!$cases->getTotalCumulativeCases($day)) {
			return '';
		}

		// $states = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
		$states = ['AC', 'AP', 'AM', 'PA', 'RO', 'RR', 'TO', 'AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE', 'DF', 'GO', 'MT', 'MS', 'ES', 'MG', 'RJ', 'SP', 'PR', 'RS', 'SC'];

		$row = "!rowspan=2 style='vertical-align:top'| " . $day->format('M j') . "\n! Cases\n";

		foreach ($states as $key => $state) {
			$row .= !$key ? '| ' : '|| ';

			$reportedCase = $cases->getReportedCase($day, $state);
			$row .= ($reportedCase && $reportedCase->cumulativeCases ? $reportedCase->cumulativeCases : '') . ' ';
		}

		$row .= "\n";
		$row .= '!rowspan=2| ' . ($cases->getTotalNewCases($day) ? '+' . $cases->getTotalNewCases($day) : '=') . "\n";
		$row .= '!rowspan=2| ' . $cases->getTotalCumulativeCases($day) . "\n";

		$row .= '|rowspan=2| ' . ($cases->getTotalCumulativeDeaths($day) ? ($cases->getTotalNewDeaths($day) ? '+' . $cases->getTotalNewDeaths($day) : '=') : '')  . "\n";
		$row .= '|rowspan=2| ' . ($cases->getTotalCumulativeDeaths($day) ?: '') . "\n";

		$row .= "|-\n! Deaths\n";

		foreach ($states as $key => $state) {
			$row .= !$key ? '| ' : '|| ';

			$reportedCase = $cases->getReportedCase($day, $state);
			$row .= ($reportedCase && $reportedCase->cumulativeDeaths ? $reportedCase->cumulativeDeaths : '') . ' ';
		}

		$row .= "\n|-\n";

		return $row;		
	}

	private function buildHeader()
	{
		return <<<HEADER
<noinclude>
<div style='float: right;'>{{VEFriendly}}</div>
{{main|2020 coronavirus pandemic in Brazil}}

== Update instructions ==
This table is populated using data provided by the Brazilian Ministry of Health.<br/>
Please '''do *not*''' update it with the the state's local data, as this might cause data inconsistencies.

You can automatically generate this entire page using the PHP script found on https://github.com/hagnat/covid

== Brazil medical cases and deaths ==
</noinclude>
{| class="wikitable mw-datatable mw-collapsible" style="font-size:80%; text-align: center;"
|+ style="font-size:125%" |{{nowrap|COVID-19 cases and deaths in Brazil, by state({{navbar|2019–20 coronavirus pandemic data/Brazil medical cases|mini=1|nodiv=1}})}}
!rowspan=2 colspan=2|
!colspan=7| [[North_Region,_Brazil|North]]
!colspan=9| [[Northeast_Region,_Brazil|Northeast]]
!colspan=4| [[Central-West_Region,_Brazil|Central-West]]
!colspan=4| [[Southeast_Region,_Brazil|Southeast]]
!colspan=3| [[South_Region,_Brazil|South]]
!colspan=2| Cases
!colspan=2| Deaths
|-
! {{flagicon|Acre}} <br/> [[Acre (state)|AC]] 
! {{flagicon|Amapá}} <br/> [[Amapá|AP]]
! {{flagicon|Amazonas}} <br/> [[Amazonas (Brazilian state)|AM]]
! {{flagicon|Pará}} <br/> [[Pará|PA]]
! {{flagicon|Rondônia}} <br/> [[Rondônia|RO]]
! {{flagicon|Roraima}} <br/> [[Roraima|RR]]
! {{flagicon|Tocantins}} <br/> [[Tocantins|TO]]

! {{flagicon|Alagoas}} <br/> [[Alagoas|AL]]
! {{flagicon|Bahia}} <br/> [[Bahia|BA]]
! {{flagicon|Ceará}} <br/> [[Ceará|CE]]
! {{flagicon|Maranhão}} <br/> [[Maranhão|MA]]
! {{flagicon|Paraíba}} <br/> [[Paraíba|PB]]
! {{flagicon|Pernambuco}} <br/> [[Pernambuco|PE]]
! {{flagicon|Piauí}} <br/> [[Piauí|PI]]
! {{flagicon|Rio Grande do Norte}} <br/> [[Rio Grande do Norte|RN]]
! {{flagicon|Sergipe}} <br/> [[Sergipe|SE]]

! {{flagicon|Distrito Federal}} <br/> [[Federal District (Brazil)|DF]]
! {{flagicon|Goiás}} <br/> [[Goiás|GO]]
! {{flagicon|Mato Grosso}} <br/> [[Mato Grosso|MT]]
! {{flagicon|Mato Grosso do Sul}} <br/> [[Mato Grosso do Sul|MS]]

! {{flagicon|Espírito Santo}} <br/> [[Espírito Santo|ES]]
! {{flagicon|Minas Gerais}} <br/> [[Minas Gerais|MG]]
! {{flagicon|Rio de Janeiro}} <br/> [[Rio de Janeiro (state)|RJ]] 
! {{flagicon|São Paulo}} <br/> [[São Paulo (state)|SP]]

! {{flagicon|Paraná}} <br/> [[Paraná (state)|PR]]
! {{flagicon|Rio Grande do Sul}} <br/> [[Rio Grande do Sul|RS]]
! {{flagicon|Santa Catarina}} <br/> [[Santa Catarina (state)|SC]]

! New
! Total
! New
! Total
|-

HEADER;
	}

	private function buildFooter()
	{
		return <<<FOOTER
|-
!rowspan=2 colspan=2|
! {{flagicon|Acre}} <br/> [[Acre (state)|AC]] 
! {{flagicon|Amapá}} <br/> [[Amapá|AP]]
! {{flagicon|Amazonas}} <br/> [[Amazonas (Brazilian state)|AM]]
! {{flagicon|Pará}} <br/> [[Pará|PA]]
! {{flagicon|Rondônia}} <br/> [[Rondônia|RO]]
! {{flagicon|Roraima}} <br/> [[Roraima|RR]]
! {{flagicon|Tocantins}} <br/> [[Tocantins|TO]]

! {{flagicon|Alagoas}} <br/> [[Alagoas|AL]]
! {{flagicon|Bahia}} <br/> [[Bahia|BA]]
! {{flagicon|Ceará}} <br/> [[Ceará|CE]]
! {{flagicon|Maranhão}} <br/> [[Maranhão|MA]]
! {{flagicon|Paraíba}} <br/> [[Paraíba|PB]]
! {{flagicon|Pernambuco}} <br/> [[Pernambuco|PE]]
! {{flagicon|Piauí}} <br/> [[Piauí|PI]]
! {{flagicon|Rio Grande do Norte}} <br/> [[Rio Grande do Norte|RN]]
! {{flagicon|Sergipe}} <br/> [[Sergipe|SE]]

! {{flagicon|Distrito Federal}} <br/> [[Federal District (Brazil)|DF]]
! {{flagicon|Goiás}} <br/> [[Goiás|GO]]
! {{flagicon|Mato Grosso}} <br/> [[Mato Grosso|MT]]
! {{flagicon|Mato Grosso do Sul}} <br/> [[Mato Grosso do Sul|MS]]

! {{flagicon|Espírito Santo}} <br/> [[Espírito Santo|ES]]
! {{flagicon|Minas Gerais}} <br/> [[Minas Gerais|MG]]
! {{flagicon|Rio de Janeiro}} <br/> [[Rio de Janeiro (state)|RJ]] 
! {{flagicon|São Paulo}} <br/> [[São Paulo (state)|SP]]

! {{flagicon|Paraná}} <br/> [[Paraná (state)|PR]]
! {{flagicon|Rio Grande do Sul}} <br/> [[Rio Grande do Sul|RS]]
! {{flagicon|Santa Catarina}} <br/> [[Santa Catarina (state)|SC]]

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
{{note|1}} Official data provided by the Brazilian Ministry of Health <ref>{{cite web|url=https://covid.saude.gov.br/|title=Ministério da Saúde|date=April 2020}}</ref>.
|-
|}
<noinclude>
== References ==
{{reflist|colwidth=30em}}

== External links ==
* https://covid.saude.gov.br/ – Ministry of Health Statistics Panel, updated daily
* https://github.com/hagnat/covid - tool to update this template

{{2019-nCoV|state=expanded}}
[[Category:2020 coronavirus pandemic in Brazil templates]]
</noinclude>

FOOTER;
	}
}