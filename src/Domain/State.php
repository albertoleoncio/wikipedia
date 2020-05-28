<?php

declare(strict_types=1);

namespace App\Domain;

final class State
{
    private $name;
    private $code;
    private $region;
    private $wikipediaEntry;
    private $wikipediaFlag;

    public function __construct(string $name, string $code, Region $region, ?string $wikipediaEntry, ?string $wikipediaFlag)
    {
        $this->name = $name;
        $this->code = mb_strtoupper($code);
        $this->region = $region;
        $this->wikipediaEntry = $wikipediaEntry ?? $name;
        $this->wikipediaFlag = $wikipediaFlag ?? $name;
    }

    public function equals(State $state): bool
    {
        return $this->code() === $state->code();
    }

    public function compare(State $state): int
    {
        return $this->region()->compare($state->region())
            ?: $this->name() <=> $state->name();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function region(): Region
    {
        return $this->region;
    }

    public function fromRegion(Region $region): bool
    {
        return $this->region()->equals($region);
    }

    public function wikipediaEntry(): string
    {
        return $this->wikipediaEntry;
    }

    public function wikipediaFlag(): string
    {
        return $this->wikipediaFlag;
    }
}
