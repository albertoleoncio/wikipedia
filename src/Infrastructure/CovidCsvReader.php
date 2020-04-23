<?php

namespace App\Infrastructure;

use App\Domain\ReportedCase;
use App\Domain\ReportedCases;

class CovidCsvReader
{
	public function read(string $filename): ReportedCases
	{
		$reportedCases = new ReportedCases;

		if (!($handle = fopen($filename, "r"))) {
			die ('Unable to open CSV file');
		}

		$headers = null;
		while (false !== ($data = fgetcsv($handle, null, ";"))) {
			if (null === $headers) {
				$headers = $data;
				continue;
			}

			$row = array_combine($headers, $data);

			$reportedCases->add($this->parseRow($row));
		}
		
		fclose($handle);


		return $reportedCases;		
	}

	private function parseRow(array $data): ReportedCase
	{
		$reportedCase = new ReportedCase;
		
		$reportedCase->day = \DateTime::createFromFormat('d/m/Y', $data['data']) ?: \DateTime::createFromFormat('Y-m-d', $data['data']);
		$reportedCase->state = $data['estado'];
		$reportedCase->region = $data['regiao'];
		$reportedCase->newCases = $data['casosNovos'];
		$reportedCase->cumulativeCases = $data['casosAcumulados'];
		$reportedCase->newDeaths = $data['obitosNovos'];
		$reportedCase->cumulativeDeaths = $data['obitosAcumulados'];

		return $reportedCase;
	}
}
