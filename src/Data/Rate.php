<?php

declare(strict_types=1);

namespace AlexBabintsev\Nbu\Data;

use Carbon\CarbonImmutable;

/**
 * One currency's official rate against the hryvnia on a given day.
 */
final readonly class Rate
{
    public function __construct(
        /** ISO 4217 alphabetic code, e.g. "USD". */
        public string $code,
        /** ISO 4217 numeric code, e.g. 840. */
        public int $numericCode,
        /** Hryvnia per one unit of the currency. */
        public float $rate,
        /** The currency's Ukrainian name as the NBU spells it. */
        public string $name,
        /** The banking day the rate applies to. */
        public CarbonImmutable $date,
    ) {}

    /**
     * Build a rate from one entry of the NBU response.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            code: (string) ($payload['cc'] ?? ''),
            numericCode: (int) ($payload['r030'] ?? 0),
            rate: (float) ($payload['rate'] ?? 0),
            name: (string) ($payload['txt'] ?? ''),
            // The API formats dates as d.m.Y rather than ISO.
            date: CarbonImmutable::createFromFormat('d.m.Y', (string) ($payload['exchangedate'] ?? ''))
                ?: CarbonImmutable::today(),
        );
    }

    /**
     * Convert an amount in this currency to hryvnia.
     */
    public function toUah(float $amount): float
    {
        return $amount * $this->rate;
    }

    /**
     * Convert an amount in hryvnia to this currency.
     */
    public function fromUah(float $amount): float
    {
        return $this->rate === 0.0 ? 0.0 : $amount / $this->rate;
    }

    /**
     * @return array{code: string, numeric_code: int, rate: float, name: string, date: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'numeric_code' => $this->numericCode,
            'rate' => $this->rate,
            'name' => $this->name,
            'date' => $this->date->toDateString(),
        ];
    }
}
