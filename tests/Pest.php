<?php

declare(strict_types=1);

use AlexBabintsev\Nbu\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * One entry of an NBU response.
 *
 * @return array<string, mixed>
 */
function nbuRate(string $code = 'USD', float $rate = 44.6144, string $date = '21.08.2026'): array
{
    return [
        'r030' => 840,
        'txt' => 'Долар США',
        'rate' => $rate,
        'cc' => $code,
        'exchangedate' => $date,
        'special' => null,
    ];
}
