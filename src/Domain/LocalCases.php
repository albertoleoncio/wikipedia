<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonInterface as DateTimeInterface;

final class LocalCases
{
    private $reportedCases = [];

    public function add(LocalCase ...$cases)
    {
        foreach ($cases as $case) {
            $this->reportedCases[$case->day->format('Y-m-d')][$case->macroRegion][$case->microRegion][] = $case;
        }
    }

    public function filterByDate(DateTimeInterface $dayFilter): LocalCases
    {
        $filteredCases = new LocalCases();

        foreach ($this->reportedCases as $day => $dayContents) {
            if ($day === $dayFilter->format('Y-m-d')) {
                foreach ($dayContents as $macroRegionKey => $macroRegionCases) {
                    foreach ($macroRegionCases as $microRegionKey => $microRegionCases) {
                        $filteredCases->add(...$microRegionCases);
                    }
                }

                return $filteredCases;
            }
        }

        return $filteredCases;
    }

    public function filterByMacroRegion(string $macroRegionFilter): LocalCases
    {
        $filteredCases = new LocalCases();

        foreach ($this->reportedCases as $day => $dayContents) {
            foreach ($dayContents as $macroRegionKey => $macroRegionCases) {
                if (mb_strtoupper($macroRegionKey) === mb_strtoupper($macroRegionFilter)) {
                    foreach ($macroRegionCases as $microRegionKey => $microRegionCases) {
                        $filteredCases->add(...$microRegionCases);
                    }

                    // return to first loop
                    break;
                }
            }
        }

        return $filteredCases;
    }

    public function filterByMicroRegion(string $microRegionFilter): LocalCases
    {
        $filteredCases = new LocalCases();

        foreach ($this->reportedCases as $day => $dayContents) {
            foreach ($dayContents as $macroRegionKey => $macroRegionCases) {
                foreach ($macroRegionCases as $microRegionKey => $microRegionCases) {
                    if (mb_strtoupper($microRegionKey) === mb_strtoupper($microRegionFilter)) {
                        $filteredCases->add(...$microRegionCases);

                        // return to first loop
                        break 2;
                    }
                }
            }
        }

        return $filteredCases;
    }

    public function getTotalConfirmedCases(): int
    {
        $confirmedCases = 0;
        foreach ($this->reportedCases as $day => $dayContents) {
            foreach ($dayContents as $macroRegionKey => $macroRegionContents) {
                foreach ($macroRegionContents as $microRegionKey => $microRegionContents) {
                    foreach ($microRegionContents as $case) {
                        $confirmedCases += $case->confirmedCases;
                    }
                }
            }
        }

        return $confirmedCases;
    }

    public function getTotalConfirmedDeaths(): int
    {
        $confirmedDeaths = 0;
        foreach ($this->reportedCases as $day => $dayContents) {
            foreach ($dayContents as $macroRegionKey => $macroRegionContents) {
                foreach ($macroRegionContents as $microRegionKey => $microRegionContents) {
                    foreach ($microRegionContents as $case) {
                        $confirmedDeaths += $case->confirmedDeaths;
                    }
                }
            }
        }

        return $confirmedDeaths;
    }
}
