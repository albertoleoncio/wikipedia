<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable as DateTimeImmutable;
use Carbon\CarbonInterface as DateTimeInterface;
use Throwable;

final class ReportedCase
{
    private const NATIONAL_REGION = 'BRASIL';

    private $region;
    private $state;
    private $city;
    private $day;
    private $cumulativeCases;
    private $cumulativeDeaths;
    private $cumulativeRecoveries;

    private function __construct(
        Region $region,
        ?State $state,
        ?City $city,
        DateTimeInterface $day,
        int $cumulativeCases,
        int $cumulativeDeaths,
        int $cumulativeRecoveries
    ) {
        $this->region = $region;
        $this->state = $state;
        $this->city = $city;
        $this->day = $day;
        $this->cumulativeCases = $cumulativeCases;
        $this->cumulativeDeaths = $cumulativeDeaths;
        $this->cumulativeRecoveries = $cumulativeRecoveries;
    }

    public static function fromCsv(array $data): ReportedCase
    {
        $region = Region::fromName($data['regiao'] ?? '');
        $state = States::english()->findByCode($data['estado'] ?? null);

        $cityCode = intval($data['codmun'] ?? 0);
        if ($cityCode) {
            $city = City::fromCode($cityCode)
                ?? City::create(
                    $cityCode,
                    $data['municipio'] ?? '',
                    intval(
                        $data['populacaoTCU2019'] ?? 0
                    )
                );
        } else {
            $city = null;
        }

        try {
            $day = DateTimeImmutable::createFromFormat('!d/m/Y', $data['data']);
        } catch (Throwable $e) {
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', $data['data']);
        }

        $cumulativeCases = intval($data['casosAcumulados']
            ?? $data['casosAcumulado']
            ?? 0);

        $cumulativeDeaths = intval($data['obitosAcumulados']
            ?? $data['obitosAcumulado']
            ?? 0);

        $cumulativeRecoveries = intval($data['Recuperadosnovos']
            ?? 0);

        return new static($region, $state, $city, $day, $cumulativeCases, $cumulativeDeaths, $cumulativeRecoveries);
    }

    public function region(): Region
    {
        return $this->region;
    }

    public function state(): ?State
    {
        return $this->state;
    }

    public function city(): ?City
    {
        return $this->city;
    }

    public function day(): DateTimeInterface
    {
        return $this->day;
    }

    public function cumulativeCases(): int
    {
        return intval($this->cumulativeCases) ?: 0;
    }

    public function newCases(ReportedCases $cases): int
    {
        $cases = $cases->filterByCase($this)->filterByDate($this->day()->sub(1, 'day'));

        return $this->cumulativeCases() - $cases->getTotalCumulativeCases();
    }

    public function cumulativeDeaths(): int
    {
        return intval($this->cumulativeDeaths) ?: 0;
    }

    public function newDeaths(ReportedCases $cases): int
    {
        $cases = $cases->filterByCase($this)->filterByDate($this->day()->sub(1, 'day'));

        return $this->cumulativeDeaths() - $cases->getTotalCumulativeDeaths();
    }

    public function cumulativeRecoveries(): int
    {
        return intval($this->cumulativeRecoveries) ?: 0;
    }

    public function newRecoveries(ReportedCases $cases): int
    {
        $cases = $cases->filterByCase($this)->filterByDate($this->day()->sub(1, 'day'));

        return $this->cumulativeRecoveries() - $cases->getTotalCumulativeRecoveries();
    }

    public function isNationalLevel(): bool
    {
        return $this->region()->equals(Region::country())
            && !$this->isStateLevel()
            && !$this->isLocalLevel();
    }

    public function isStateLevel(): bool
    {
        return null !== $this->state()
            && !$this->isLocalLevel();
    }

    public function isLocalLevel(): bool
    {
        return null !== $this->city;
    }

    public function sameRegion(Region $region): bool
    {
        return $this->region()->equals($region);
    }

    public function sameState(State $state): bool
    {
        return $this->state() && $this->state()->equals($state);
    }

    public function sameCity(City $city): bool
    {
        return $this->city() && $this->city()->equals($city);
    }

    public function sameDay(DateTimeInterface $day)
    {
        return $this->day()->format('Y-m-d') === $day->format('Y-m-d');
    }
}
