<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\ReportedCase;
use App\Domain\ReportedCases;

final class CovidCsvReader
{
    public function read(string $filename, string $csvSeparator): ReportedCases
    {
        $reportedCases = new ReportedCases();

        if (!($handle = fopen($filename, 'r'))) {
            die('Unable to open CSV file');
        }

        $headers = fgetcsv($handle, null, $csvSeparator);

        while (false !== ($data = fgetcsv($handle, null, $csvSeparator))) {
            $row = array_combine($headers, $data);
            $case = ReportedCase::fromCsv($row);

            $reportedCases->add($case);
        }

        fclose($handle);

        return $reportedCases;
    }
}
