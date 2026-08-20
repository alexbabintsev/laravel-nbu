<?php

declare(strict_types=1);

use AlexBabintsev\Nbu\Data\Rate;
use AlexBabintsev\Nbu\Exceptions\NbuException;
use AlexBabintsev\Nbu\Facades\Nbu;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('fetching rates', function (): void {
    it('reads a currency rate', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        $rate = Nbu::rate('USD');

        expect($rate)->toBeInstanceOf(Rate::class)
            ->and($rate->code)->toBe('USD')
            ->and($rate->rate)->toBe(44.6144)
            ->and($rate->name)->toBe('Долар США')
            ->and($rate->date->toDateString())->toBe('2026-08-21');
    });

    it('accepts a lowercase code', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        expect(Nbu::rate('usd')?->code)->toBe('USD');
    });

    it('returns null for a currency the bank does not publish', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        expect(Nbu::rate('XYZ'))->toBeNull();
    });

    it('fails loudly when asked to', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        Nbu::rateOrFail('XYZ');
    })->throws(NbuException::class);

    it('returns every published rate keyed by code', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([
            nbuRate('USD', 44.6144),
            nbuRate('EUR', 48.2),
        ])]);

        expect(Nbu::rates())->toHaveKeys(['USD', 'EUR'])->toHaveCount(2);
    });
});

describe('a rate on a given date', function (): void {
    it('asks the bank for that day', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate(date: '01.03.2026')])]);

        Nbu::rate('USD', '2026-03-01');

        Http::assertSent(fn ($request): bool => $request['date'] === '20260301');
    });

    it('accepts a DateTime as well as a string', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate(date: '01.03.2026')])]);

        Nbu::rate('USD', new DateTimeImmutable('2026-03-01'));

        Http::assertSent(fn ($request): bool => $request['date'] === '20260301');
    });
});

describe('caching', function (): void {
    it('asks the bank once for the same day', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        Nbu::rate('USD');
        Nbu::rate('EUR');
        Nbu::rates();

        Http::assertSentCount(1);
    });

    it("expires today's rates at midnight, when the next banking day starts", function (): void {
        CarbonImmutable::setTestNow('2026-08-21 14:00:00');
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        Nbu::rate('USD');

        // Still cached just before midnight...
        CarbonImmutable::setTestNow('2026-08-21 23:59:00');
        Nbu::rate('USD');
        Http::assertSentCount(1);
    });

    it('keeps a past date indefinitely, since a published rate never changes', function (): void {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00');
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate(date: '01.03.2026')])]);

        Nbu::rate('USD', '2026-03-01');

        CarbonImmutable::setTestNow('2026-11-21 10:00:00');
        Nbu::rate('USD', '2026-03-01');

        Http::assertSentCount(1);
    });

    it('caches each date separately', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        Nbu::rate('USD', '2026-03-01');
        Nbu::rate('USD', '2026-03-02');

        Http::assertSentCount(2);
    });
});

describe('when the bank is unreachable', function (): void {
    it('serves the last known rates', function (): void {
        // A sequence, not two fake() calls: re-faking adds a stub rather than
        // replacing the first, so the second call would still get a 200.
        Http::fake(['bank.gov.ua/*' => Http::sequence()
            ->push([nbuRate('USD', 44.6144)])
            ->pushStatus(503)]);

        Nbu::rate('USD');

        // A new day, and this time the bank is down.
        CarbonImmutable::setTestNow(CarbonImmutable::tomorrow()->addHours(9));

        $rate = Nbu::rate('USD');

        expect($rate?->rate)->toBe(44.6144)
            // The caller can tell this is not a rate the bank confirmed today.
            ->and(Nbu::isStale())->toBeTrue();
    });

    it('reports fresh rates as not stale', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        Nbu::rate('USD');

        expect(Nbu::isStale())->toBeFalse();
    });

    it('throws when there is nothing cached to fall back on', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response(null, 503)]);

        Nbu::rates();
    })->throws(NbuException::class);

    it('survives a timeout, not just an error response', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::sequence()
            ->push([nbuRate('USD', 40.0)])
            ->pushFailedConnection()]);

        Nbu::rate('USD');

        CarbonImmutable::setTestNow(CarbonImmutable::tomorrow()->addHours(9));

        expect(Nbu::rate('USD')?->rate)->toBe(40.0)
            ->and(Nbu::isStale())->toBeTrue();
    });
});

describe('conversion', function (): void {
    beforeEach(function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate('USD', 44.6144)])]);
    });

    it('converts a currency into hryvnia', function (): void {
        expect(Nbu::toUah(10, 'USD'))->toBe(446.144);
    });

    it('converts hryvnia into a currency', function (): void {
        expect(round(Nbu::fromUah(446.144, 'USD'), 4))->toBe(10.0);
    });

    it('does not divide by a zero rate', function (): void {
        $rate = new Rate('XXX', 0, 0.0, 'Нуль', CarbonImmutable::today());

        expect($rate->fromUah(100))->toBe(0.0);
    });
});

describe('cache management', function (): void {
    it('forgets a cached day', function (): void {
        Http::fake(['bank.gov.ua/*' => Http::response([nbuRate()])]);

        Nbu::rate('USD');
        Nbu::forget(CarbonImmutable::today());
        Nbu::rate('USD');

        Http::assertSentCount(2);
    });
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Cache::flush();
});
