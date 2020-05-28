<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable as DateTimeImmutable;
use Carbon\CarbonInterface as DateTimeInterface;
use Countable;

final class ReportedCases implements Countable
{
    private const DAY_INDEX_FORMAT = 'Y-m-d';

    private $cases = [];
    private $indexByDate = [];
    private $indexByRegion = [];
    private $indexByState = [];
    private $indexByCity = [];

    public function __construct(ReportedCase ...$cases)
    {
        foreach ($cases as $case) {
            $this->add($case);
        }
    }

    public function count(): int
    {
        return count($this->cases);
    }

    public function getArrayCopy(): array
    {
        return $this->cases;
    }

    public function add(ReportedCase ...$cases)
    {
        foreach ($cases as $case) {
            if (0 === $case->cumulativeCases()) {
                continue;
            }

            // if ($this->contains($case)) {
            //     continue;
            // }

            $this->cases[] = $case;

            $this->indexByDate($case);
            $this->indexByRegion($case);
            $this->indexByState($case);
            $this->indexByCity($case);
        }
    }

    public function contains(ReportedCase $case): bool
    {
        if (empty($this->indexByDate[$case->day()->format(static::DAY_INDEX_FORMAT)])) {
            return false;
        }
        if (empty($this->indexByRegion[$case->region()->code()])) {
            return false;
        }
        if ($case->state() && empty($this->indexByState[$case->state()->code()])) {
            return false;
        }
        if ($case->city() && empty($this->indexByCity[$case->city()->code()])) {
            return false;
        }

        return true;
    }

    public function merge(ReportedCases $cases)
    {
        $mergedCases = clone $this;

        $mergedCases->add(...$cases->getArrayCopy());

        return $mergedCases;
    }

    public function nationalCases(): ReportedCases
    {
        $cases = array_filter($this->cases, function (ReportedCase $case) {
            return $case->isNationalLevel();
        });

        return new ReportedCases(...$cases);
    }

    public function stateCases(): ReportedCases
    {
        $cases = array_filter($this->cases, function (ReportedCase $case) {
            return $case->isStateLevel();
        });

        return new ReportedCases(...$cases);
    }

    public function localCases(): ReportedCases
    {
        $cases = array_filter($this->cases, function (ReportedCase $case) {
            return $case->isLocalLevel();
        });

        return new ReportedCases(...$cases);
    }

    public function filterByRegion(Region $region): ReportedCases
    {
        $cases = $this->indexByRegion[$region->code()] ?? [];

        return new ReportedCases(...$cases);
    }

    public function filterByState(State $state): ReportedCases
    {
        $cases = $this->indexByState[$state->code()] ?? [];

        return new ReportedCases(...$cases);
    }

    public function filterByCity(City $city): ReportedCases
    {
        $cases = $this->indexByCity[$city->code()] ?? [];

        return new ReportedCases(...$cases);
    }

    public function filterByDate(DateTimeInterface $date): ReportedCases
    {
        $cases = $this->indexByDate[$date->format(static::DAY_INDEX_FORMAT)] ?? [];

        return new ReportedCases(...$cases);
    }

    public function filterByCase(ReportedCase $case): ReportedCases
    {
        if ($case->isLocalLevel()) {
            return $this->filterByCity($case->city()->code());
        }

        if ($case->isStateLevel()) {
            return $this->filterByState($case->state());
        }

        return $this->filterByRegion($case->region());
    }

    public function getTotalNewCases(): int
    {
        return array_reduce($this->getLastReportedCases()->getArrayCopy(), function (int $carry, ReportedCase $case) {
            $carry += $case->newCases($this);

            return $carry;
        }, $initial = 0);
    }

    public function getTotalCumulativeCases(): int
    {
        return array_reduce($this->getLastReportedCases()->getArrayCopy(), function (int $carry, ReportedCase $case) {
            $carry += $case->cumulativeCases();

            return $carry;
        }, $initial = 0);
    }

    public function getTotalNewDeaths(): int
    {
        return array_reduce($this->getLastReportedCases()->getArrayCopy(), function (int $carry, ReportedCase $case) {
            $carry += $case->newDeaths($this);

            return $carry;
        }, $initial = 0);
    }

    public function getTotalCumulativeDeaths(): int
    {
        return array_reduce($this->getLastReportedCases()->getArrayCopy(), function (int $carry, ReportedCase $case) {
            $carry += $case->cumulativeDeaths();

            return $carry;
        }, $initial = 0);
    }

    public function getTotalNewRecoveries(): int
    {
        return array_reduce($this->getLastReportedCases()->getArrayCopy(), function (int $carry, ReportedCase $case) {
            $carry += $case->newRecoveries($this);

            return $carry;
        }, $initial = 0);
    }

    public function getTotalCumulativeRecoveries(): int
    {
        return array_reduce($this->getLastReportedCases()->getArrayCopy(), function (int $carry, ReportedCase $case) {
            $carry += $case->cumulativeRecoveries();

            return $carry;
        }, $initial = 0);
    }

    public function getLastReportedDate(): DateTimeInterface
    {
        $dates = array_keys($this->indexByDate);
        $lastReportedDate = end($dates);

        return DateTimeImmutable::createFromFormat('!' . static::DAY_INDEX_FORMAT, $lastReportedDate ?: '2020-02-24');
    }

    public function getFirstReportedDate(): DateTimeInterface
    {
        $dates = array_keys($this->indexByDate);
        $firstReportedDate = current($dates);

        return DateTimeImmutable::createFromFormat('!' . static::DAY_INDEX_FORMAT, $firstReportedDate ?: '2020-02-24');
    }

    private function indexByDate(ReportedCase $case)
    {
        $this->indexByDate[$case->day()->format(static::DAY_INDEX_FORMAT)][] = $case;

        ksort($this->indexByDate);
    }

    private function indexByRegion(ReportedCase $case)
    {
        $this->indexByRegion[$case->region()->code()][] = $case;
    }

    private function indexByState(ReportedCase $case)
    {
        if (null === $case->state()) {
            return;
        }

        $this->indexByState[$case->state()->code()][] = $case;
    }

    private function indexByCity(ReportedCase $case)
    {
        if (null === $case->city()) {
            return;
        }

        $this->indexByCity[$case->city()->code()][] = $case;
    }

    private function getLastReportedCases(): ReportedCases
    {
        return $this->filterByDate($this->getLastReportedDate());
    }
}
