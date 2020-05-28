<?php

declare(strict_types=1);

namespace App\Domain;

final class Region
{
    private $priority;
    private $code;
    private $englishName;
    private $portugueseName;

    private static $instances = null;

    private function __construct(int $priority, string $code, string $englishName, string $portugueseName)
    {
        $this->priority = $priority;
        $this->code = $code;
        $this->englishName = $englishName;
        $this->portugueseName = $portugueseName;
    }

    public static function regions(): array
    {
        return [
            static::north(),
            static::northeast(),
            static::centralWest(),
            static::southeast(),
            static::south(),
        ];
    }

    public static function country(): Region
    {
        return static::instance('BR');
    }

    public static function north(): Region
    {
        return static::instance('N');
    }

    public static function northeast(): Region
    {
        return static::instance('NE');
    }

    public static function centralWest(): Region
    {
        return static::instance('CW');
    }

    public static function southeast(): Region
    {
        return static::instance('SE');
    }

    public static function south(): Region
    {
        return static::instance('S');
    }

    public static function fromName(string $name): Region
    {
        switch (mb_strtolower($name)) {
            case 'north':
            case 'norte':
                return static::north();
            case 'northeast':
            case 'nordeste':
                return static::northeast();
            case 'central-west':
            case 'centro-oeste':
                return static::centralWest();
            case 'southeast':
            case 'sudeste':
                return static::southeast();
            case 'south':
            case 'sul':
                return static::south();
            case 'brazil':
            case 'brasil':
            default:
                return static::country();
        }
    }

    public function equals(Region $region): bool
    {
        return $this->code === $region->code;
    }

    public function compare(Region $region): int
    {
        return $this->priority <=> $region->priority;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function english(): string
    {
        return $this->englishName;
    }

    public function portuguese(): string
    {
        return $this->portugueseName;
    }

    private static function instance(string $code): Region
    {
        if (null === static::$instances) {
            static::$instances = [
                'BR' => new static(0, 'BR', 'Brazil', 'Brasil'),
                'N' => new static(1, 'N', 'North', 'Norte'),
                'NE' => new static(2, 'NE', 'Northesat', 'Nordeste'),
                'CW' => new static(3, 'CW', 'Central-West', 'Centro-Oeste'),
                'SE' => new static(4, 'SE', 'Southeast', 'Sudeste'),
                'S' => new static(5, 'S', 'South', 'Sul'),
            ];
        }

        return static::$instances[$code];
    }
}
