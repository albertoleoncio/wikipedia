<?php

declare(strict_types=1);

namespace App\Domain;

final class City
{
    public const MANAUS = 130260;
    public const BELEM = 150140;
    public const FORTALEZA = 230440;
    public const RECIFE = 261160;
    public const BELO_HORIZONTE = 310620;
    public const VITORIA = 320530;
    public const RIO_DE_JANEIRO = 330455;
    public const SAO_PAULO = 355030;
    public const CURITIBA = 410690;
    public const FLORIANOPOLIS = 420540;
    public const PORTO_ALEGRE = 431490;

    private $name;
    private $code;
    private $population;

    private static $instances = [];

    private function __construct(int $code, string $name, int $population)
    {
        $this->name = $name;
        $this->code = $code;
        $this->population = $population;
    }

    public static function create(int $code, string $name, int $population)
    {
        if (empty(static::$instances[$code])) {
            static::$instances[$code] = new static($code, $name, $population);
        }

        // updates the instance's name, if possible
        if (!static::$instances[$code]->name && $name) {
            static::$instances[$code]->name = $name;
        }

        // updates the instance's population, if possible
        if (static::$instances[$code]->population < $population) {
            static::$instances[$code]->population = $population;
        }

        return static::$instances[$code];
    }

    public static function fromCode(int $code)
    {
        return static::$instances[$code] ?? null;
    }

    public function compare(City $city): int
    {
        return $this->population <=> $city->population;
    }

    public function equals(City $city): bool
    {
        return $this->code() === $city->code();
    }

    public function code(): int
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function perMillion(int $value): float
    {
        return $this->population
            ? floor($value / $this->population * 10000) / 1000000
            : 0;
    }
}
