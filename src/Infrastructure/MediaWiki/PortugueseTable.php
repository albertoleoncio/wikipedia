<?php

namespace App\Infrastructure\MediaWiki;

use App\Application\ParserInterface;
use App\Domain\ReportedCase;
use App\Domain\ReportedCases;

class PortugueseTable implements ParserInterface
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

		$row = "\n!rowspan=2 style='vertical-align:top'| " . $day->format('d/m') . "\n! Casos\n";

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

		$row .= "|-\n! Mortes\n";

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
{| class="wikitable mw-datatable mw-collapsible" style="font-size:80%; text-align: center;"
|+ style="font-size:125%" |{{nowrap|Casos e mortes pela COVID-19 no Brasil, por estado ({{navbar|{{subst:PAGENAME}}|mini=1|nodiv=1}})}}
!rowspan=2 colspan=2|
!colspan=7| [[Região Norte do Brasil|Norte]]
!colspan=9| [[Região Nordeste do Brasil|Nordeste]]
!colspan=4| [[Região Centro-Oeste do Brasil|Centro-Oeste]]
!colspan=4| [[Região Sudeste do Brasil|Sudeste]]
!colspan=3| [[Região Sul do Brasil|Sul]]
!colspan=2| Casos
!colspan=2| Mortes
|-
! {{flagicon|Acre}} <br/> [[Acre|AC]] 
! {{flagicon|Amapá}} <br/> [[Amapá|AP]]
! {{flagicon|Amazonas}} <br/> [[Amazonas|AM]]
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
! {{flagicon|Distrito Federal}} <br/> [[Distrito Federal (Brasil)|DF]]
! {{flagicon|Goiás}} <br/> [[Goiás|GO]]
! {{flagicon|Mato Grosso}} <br/> [[Mato Grosso|MT]]
! {{flagicon|Mato Grosso do Sul}} <br/> [[Mato Grosso do Sul|MS]]
! {{flagicon|Espírito Santo}} <br/> [[Espírito Santo (estado)|ES]]
! {{flagicon|Minas Gerais}} <br/> [[Minas Gerais|MG]]
! {{flagicon|Rio de Janeiro}} <br/> [[Rio de Janeiro|RJ]] 
! {{flagicon|São Paulo}} <br/> [[São Paulo|SP]]
! {{flagicon|Paraná}} <br/> [[Paraná|PR]]
! {{flagicon|Rio Grande do Sul}} <br/> [[Rio Grande do Sul|RS]]
! {{flagicon|Santa Catarina}} <br/> [[Santa Catarina|SC]]
! Novos
! Total
! Novos
! Total
|-
HEADER;
	}

	private function buildFooter()
	{
		return <<<FOOTER
|-
!rowspan=2 colspan=2|
! {{flagicon|Acre}} <br/> [[Acre|AC]] 
! {{flagicon|Amapá}} <br/> [[Amapá|AP]]
! {{flagicon|Amazonas}} <br/> [[Amazonas|AM]]
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
! {{flagicon|Distrito Federal}} <br/> [[Distrito Federal (Brasil)|DF]]
! {{flagicon|Goiás}} <br/> [[Goiás|GO]]
! {{flagicon|Mato Grosso}} <br/> [[Mato Grosso|MT]]
! {{flagicon|Mato Grosso do Sul}} <br/> [[Mato Grosso do Sul|MS]]
! {{flagicon|Espírito Santo}} <br/> [[Espírito Santo (estado)|ES]]
! {{flagicon|Minas Gerais}} <br/> [[Minas Gerais|MG]]
! {{flagicon|Rio de Janeiro}} <br/> [[Rio de Janeiro|RJ]] 
! {{flagicon|São Paulo}} <br/> [[São Paulo|SP]]
! {{flagicon|Paraná}} <br/> [[Paraná|PR]]
! {{flagicon|Rio Grande do Sul}} <br/> [[Rio Grande do Sul|RS]]
! {{flagicon|Santa Catarina}} <br/> [[Santa Catarina|SC]]
! Novos
! Total
! Novos
! Total
|-
!colspan=7| [[Região Norte do Brasil|Norte]]
!colspan=9| [[Região Nordeste do Brasil|Nordeste]]
!colspan=4| [[Região Centro-Oeste do Brasil|Centro-Oeste]]
!colspan=4| [[Região Sudeste do Brasil|Sudeste]]
!colspan=3| [[Região Sul do Brasil|Sul]]
!colspan=2| Casos
!colspan=2| Mortes
|-
| colspan="33" |
|-
| colspan="33" style="text-align: left;" | Notas:<br/>
{{nota|1}} Balanço oficial dos casos segundo o Ministério da Saúde.<ref>{{citar web|url=https://covid.saude.gov.br/|titulo=Ministério da Saúde|data=Abril 2020}}</ref>
|-
|}<noinclude>{{documentação}}</noinclude>
FOOTER;
	}
}