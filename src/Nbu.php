<?php

declare(strict_types=1);

namespace AlexBabintsev\Nbu;

use AlexBabintsev\Nbu\Data\Rate;
use AlexBabintsev\Nbu\Exceptions\NbuException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the National Bank of Ukraine's official exchange rates.
 *
 * The NBU sets one rate per currency per banking day, so results are cached
 * rather than re-fetched: today's until midnight, a past date's forever, since
 * a rate that has already been published never changes.
 *
 * When the bank cannot be reached the last successful response is served
 * instead. A page showing a price is better off with yesterday's rate than with
 * an exception, and the caller can tell the difference through
 * {@see self::isStale()} when it matters.
 */
class Nbu
{
    /**
     * Whether the last lookup came from the fallback rather than the bank.
     */
    private bool $stale = false;

    /**
     * @param  array{endpoint: string, timeout: int, cache: array{store: ?string, prefix: string, fallback_days: int}}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * The rate for one currency, or null when the NBU does not publish it.
     */
    public function rate(string $code, DateTimeInterface|string|null $date = null): ?Rate
    {
        return $this->rates($date)[mb_strtoupper($code)] ?? null;
    }

    /**
     * The rate for one currency, failing loudly when it is not published.
     *
     * @throws NbuException
     */
    public function rateOrFail(string $code, DateTimeInterface|string|null $date = null): Rate
    {
        return $this->rate($code, $date) ?? throw NbuException::unknownCurrency(mb_strtoupper($code));
    }

    /**
     * Every published rate for a date, keyed by currency code.
     *
     * @return array<string, Rate>
     *
     * @throws NbuException when the bank is unreachable and nothing is cached
     */
    public function rates(DateTimeInterface|string|null $date = null): array
    {
        $day = $this->resolveDate($date);
        $this->stale = false;

        $cached = $this->cache()->get($this->cacheKey($day));

        if (is_array($cached)) {
            return $this->hydrate($cached);
        }

        try {
            $payload = $this->fetch($day);
        } catch (Throwable $exception) {
            return $this->fallback($exception);
        }

        $this->cache()->put($this->cacheKey($day), $payload, $this->ttlFor($day));

        // Kept separately and for longer: this is what a later outage falls back
        // on, so it must outlive the day-scoped entry above.
        $this->cache()->put(
            $this->fallbackKey(),
            $payload,
            CarbonImmutable::now()->addDays($this->config['cache']['fallback_days']),
        );

        return $this->hydrate($payload);
    }

    /**
     * Convert an amount from a currency into hryvnia.
     */
    public function toUah(float $amount, string $code, DateTimeInterface|string|null $date = null): float
    {
        return $this->rateOrFail($code, $date)->toUah($amount);
    }

    /**
     * Convert an amount from hryvnia into a currency.
     */
    public function fromUah(float $amount, string $code, DateTimeInterface|string|null $date = null): float
    {
        return $this->rateOrFail($code, $date)->fromUah($amount);
    }

    /**
     * Whether the last lookup was served from the outage fallback rather than a
     * rate the bank confirmed for the date asked for.
     */
    public function isStale(): bool
    {
        return $this->stale;
    }

    /**
     * Drop everything this package has cached.
     */
    public function forget(DateTimeInterface|string|null $date = null): void
    {
        if ($date === null) {
            $this->cache()->forget($this->fallbackKey());

            return;
        }

        $this->cache()->forget($this->cacheKey($this->resolveDate($date)));
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Throwable
     */
    private function fetch(CarbonImmutable $day): array
    {
        $response = Http::timeout($this->config['timeout'])
            ->acceptJson()
            ->get($this->config['endpoint'], [
                'date' => $day->format('Ymd'),
                'json' => '',
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw NbuException::unreachable('відповідь не є коректним JSON');
        }

        return array_values(array_filter($payload, 'is_array'));
    }

    /**
     * Serve the last known rates, or rethrow when there are none.
     *
     * @return array<string, Rate>
     *
     * @throws NbuException
     */
    private function fallback(Throwable $exception): array
    {
        $cached = $this->cache()->get($this->fallbackKey());

        if (! is_array($cached)) {
            throw NbuException::unreachable($exception->getMessage());
        }

        $this->stale = true;

        return $this->hydrate($cached);
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $payload
     * @return array<string, Rate>
     */
    private function hydrate(array $payload): array
    {
        $rates = [];

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $rate = Rate::fromApi($entry);

            if ($rate->code !== '') {
                $rates[$rate->code] = $rate;
            }
        }

        return $rates;
    }

    /**
     * Today's rates expire at midnight, when the next banking day begins. A past
     * date is already settled and never changes, so it is kept indefinitely.
     */
    private function ttlFor(CarbonImmutable $day): CarbonImmutable
    {
        return $day->isToday()
            ? CarbonImmutable::tomorrow()
            : CarbonImmutable::now()->addYear();
    }

    private function resolveDate(DateTimeInterface|string|null $date): CarbonImmutable
    {
        if ($date === null) {
            return CarbonImmutable::today();
        }

        return CarbonImmutable::parse($date)->startOfDay();
    }

    private function cacheKey(CarbonImmutable $day): string
    {
        return "{$this->config['cache']['prefix']}.rates.{$day->format('Ymd')}";
    }

    private function fallbackKey(): string
    {
        return "{$this->config['cache']['prefix']}.rates.last-known";
    }

    private function cache(): CacheRepository
    {
        return Cache::store($this->config['cache']['store']);
    }
}
