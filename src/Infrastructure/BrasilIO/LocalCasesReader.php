<?php

declare(strict_types=1);

namespace App\Infrastructure\BrasilIO;

use App\Domain\LocalCase;
use App\Domain\LocalCases;
use Carbon\CarbonImmutable as DateTimeImmutable;
use Throwable;

final class LocalCasesReader
{
    private $cityMap = [];

    public function __construct(string $cityMapFilename)
    {
        $this->readCityMap($cityMapFilename);
    }

    public function read(string $filename): LocalCases
    {
        $cases = new LocalCases();

        if (!($handle = fopen($filename, 'r'))) {
            die('Unable to open CSV file');
        }

        $headers = null;
        while (false !== ($data = fgetcsv($handle, null, ','))) {
            if (null === $headers) {
                $headers = $data;

                continue;
            }

            $row = array_combine($headers, $data);

            $cases->add($this->parseRow($row));
        }

        fclose($handle);

        return $cases;
    }

    private function parseRow(array $data): LocalCase
    {
        $case = new LocalCase();

        $cityData = $this->findCity($data['city']);

        try {
            $case->day = DateTimeImmutable::createFromFormat('!d/m/Y', $data['data']);
        } catch (Throwable $e) {
            $case->day = DateTimeImmutable::createFromFormat('!Y-m-d', $data['data']);
        }

        $case->state = $data['state'];
        $case->macroRegion = $cityData['MacroRegion'];
        $case->microRegion = $cityData['MicroRegion'];
        $case->city = $cityData['City'];
        $case->confirmedCases = $data['confirmed'];
        $case->confirmedDeaths = $data['deaths'];
        $case->population = $data['estimated_population_2019'];

        return $case;
    }

    private function findCity(string $cityName): array
    {
        return $this->cityMap[$cityName]
            ?? [
                'City' => $cityName,
                'MacroRegion' => null,
                'MicroRegion' => null,
            ];
    }

    private function readCityMap(string $filename)
    {
        if (!($handle = fopen($filename, 'r'))) {
            die('Unable to open CSV file');
        }

        $this->cityMap = [];

        $headers = null;
        while (false !== ($data = fgetcsv($handle, null, ';'))) {
            if (null === $headers) {
                $headers = $data;

                continue;
            }

            $cityData = array_combine($headers, $data);

            $this->cityMap[$cityData['City']] = $cityData;
        }
    }
}
