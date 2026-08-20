<?php

declare(strict_types=1);

namespace AlexBabintsev\Nbu\Facades;

use AlexBabintsev\Nbu\Data\Rate;
use DateTimeInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Rate|null rate(string $code, DateTimeInterface|string|null $date = null)
 * @method static Rate rateOrFail(string $code, DateTimeInterface|string|null $date = null)
 * @method static array<string, Rate> rates(DateTimeInterface|string|null $date = null)
 * @method static float toUah(float $amount, string $code, DateTimeInterface|string|null $date = null)
 * @method static float fromUah(float $amount, string $code, DateTimeInterface|string|null $date = null)
 * @method static bool isStale()
 * @method static void forget(DateTimeInterface|string|null $date = null)
 *
 * @see \AlexBabintsev\Nbu\Nbu
 */
class Nbu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AlexBabintsev\Nbu\Nbu::class;
    }
}
