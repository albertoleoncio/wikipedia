<?php

namespace App\Domain;

class ReportedCases
{
	private $reportedCases = [];

	public function add(ReportedCase $case)
	{
		if (empty($this->reportedCases[$case->day->format('Y-m-d')])) {
			$this->reportedCases[$case->day->format('Y-m-d')] = [
				'data' => [],
				'_metadata' => [
					'newCases' => 0,
					'cumulativeCases' => 0,
					'newDeaths' => 0,
					'cumulativeDeaths' => 0,
				],
			];
		}

		$this->reportedCases[$case->day->format('Y-m-d')]['data'][$case->state] = $case;

		$this->reportedCases[$case->day->format('Y-m-d')]['_metadata']['newCases'] += $case->newCases;
		$this->reportedCases[$case->day->format('Y-m-d')]['_metadata']['cumulativeCases'] += $case->cumulativeCases;
		$this->reportedCases[$case->day->format('Y-m-d')]['_metadata']['newDeaths'] += $case->newDeaths;
		$this->reportedCases[$case->day->format('Y-m-d')]['_metadata']['cumulativeDeaths'] += $case->cumulativeDeaths;		
	}

	public function getReportedCase(\DateTime $day, string $state)
	{
		return isset($this->reportedCases[$day->format('Y-m-d')]['data'][$state])
			? $this->reportedCases[$day->format('Y-m-d')]['data'][$state]
			: null;
	}

	public function getTotalNewCases(\DateTime $day): int
	{
		return $this->reportedCases[$day->format('Y-m-d')]['_metadata']['newCases'] ?? 0;
	}

	public function getTotalCumulativeCases(\DateTime $day): int
	{
		return $this->reportedCases[$day->format('Y-m-d')]['_metadata']['cumulativeCases'] ?? 0;
	}

	public function getTotalNewDeaths(\DateTime $day): int
	{
		return $this->reportedCases[$day->format('Y-m-d')]['_metadata']['newDeaths'] ?? 0;
	}

	public function getTotalCumulativeDeaths(\DateTime $day): int
	{
		return $this->reportedCases[$day->format('Y-m-d')]['_metadata']['cumulativeDeaths'] ?? 0;
	}
}