[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner2-direct.svg)](https://vshymanskyy.github.io/StandWithUkraine/)
[![Made in Ukraine](https://img.shields.io/badge/made_in-Ukraine-ffd700.svg?labelColor=0057b7)](https://stand-with-ukraine.pp.ua)
[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)

# laravel-nbu

[![Tests](https://github.com/alexbabintsev/laravel-nbu/actions/workflows/run-tests.yml/badge.svg)](https://github.com/alexbabintsev/laravel-nbu/actions/workflows/run-tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/alexbabintsev/laravel-nbu.svg)](https://packagist.org/packages/alexbabintsev/laravel-nbu)
[![Downloads](https://img.shields.io/packagist/dt/alexbabintsev/laravel-nbu.svg)](https://packagist.org/packages/alexbabintsev/laravel-nbu)
[![License](https://img.shields.io/packagist/l/alexbabintsev/laravel-nbu.svg)](LICENSE.md)

The official exchange rates of the National Bank of Ukraine, for Laravel: **any
date, cached until midnight, and a fallback when the bank is unreachable**.

**[Українською](#laravel-nbu-українською)** ↓

The NBU sets one rate per currency per banking day and publishes it on an open
endpoint that needs no key. That much is simple; what a project actually has to
handle is everything around it. A rate must not be fetched twice for the same
day, a rate already published for a past date never changes and should never
expire, and a page showing a price should not fail because bank.gov.ua is down.

This wraps those three rules and nothing else. No runtime dependency beyond
Laravel itself.

## Installation

```bash
composer require alexbabintsev/laravel-nbu
```

The package works out of the box; publish the config only if you want to change
the timeout or the cache store:

```bash
php artisan vendor:publish --tag=nbu-config
```

## Usage

```php
use AlexBabintsev\Nbu\Facades\Nbu;

Nbu::rate('USD')->rate;   // 44.7006
Nbu::rate('USD')->name;   // "Долар США"
Nbu::rate('USD')->date;   // CarbonImmutable

Nbu::rate('XYZ');         // null - the NBU publishes no such currency
Nbu::rateOrFail('XYZ');   // NbuException
```

### A rate on a given date

```php
Nbu::rate('USD', '2026-03-01');        // 43.2081
Nbu::rate('EUR', now()->subMonth());   // a DateTime works too
```

### Every currency

```php
Nbu::rates();               // ['USD' => Rate, 'EUR' => Rate, ...] - 45 currencies
Nbu::rates('2026-03-01');
```

### Conversion

```php
Nbu::toUah(100, 'USD');         // 4470.06
Nbu::fromUah(4470.06, 'USD');   // 100.0

// Or from the rate object itself
Nbu::rate('USD')->toUah(100);
```

## Caching

The bank sets one rate per banking day, so the package does not ask twice:

- **today's rates** are cached until midnight, when the next banking day starts;
- **a past date** is cached indefinitely - a published rate never changes.

```php
Nbu::forget('2026-03-01');   // forget one day
Nbu::forget();               // forget the fallback
```

## When the bank is unreachable

A page showing a price should not fail because bank.gov.ua is down, so the last
known rates are served instead of throwing. Ask when it matters:

```php
$rate = Nbu::rate('USD');

if (Nbu::isStale()) {
    // Served from the fallback - the bank did not answer today
}
```

`NbuException` is thrown only when the bank is unreachable **and** nothing is
cached to fall back on.

## Configuration

```php
// config/nbu.php
'timeout' => 5,              // NBU_TIMEOUT
'cache' => [
    'store' => null,         // NBU_CACHE_STORE, null = default store
    'prefix' => 'nbu',       // NBU_CACHE_PREFIX
    'fallback_days' => 30,   // NBU_FALLBACK_DAYS
],
```

## Testing

The package uses Laravel's own HTTP client, so it fakes like anything else:

```php
Http::fake(['bank.gov.ua/*' => Http::response([
    ['r030' => 840, 'txt' => 'Долар США', 'rate' => 44.6144, 'cc' => 'USD', 'exchangedate' => '21.08.2026'],
])]);
```

## Requirements

PHP 8.2+, Laravel 11, 12 or 13.

## License

MIT. See [LICENSE.md](LICENSE.md).

---

# laravel-nbu (українською)

**[In English](#laravel-nbu)** ↑

Офіційний курс валют Національного банку України для Laravel: **курс на
будь-яку дату, кеш до півночі та резервне значення, коли банк недоступний**.

НБУ встановлює один курс на валюту за банківський день і публікує його на
відкритому ендпоінті без ключа. Це проста частина; складнощі починаються
навколо. Курс не варто запитувати двічі за той самий день, курс за минулу дату
вже не зміниться і не має протухати, а сторінка з ціною не повинна падати
через недоступний bank.gov.ua.

Пакет закриває саме ці три правила і більше нічого. Жодних залежностей, крім
самого Laravel.

## Встановлення

```bash
composer require alexbabintsev/laravel-nbu
```

Пакет працює одразу; конфіг публікуйте, лише якщо треба змінити таймаут або
сховище кешу:

```bash
php artisan vendor:publish --tag=nbu-config
```

## Використання

```php
use AlexBabintsev\Nbu\Facades\Nbu;

Nbu::rate('USD')->rate;   // 44.7006
Nbu::rate('USD')->name;   // "Долар США"
Nbu::rate('USD')->date;   // CarbonImmutable

Nbu::rate('XYZ');         // null - НБУ не публікує таку валюту
Nbu::rateOrFail('XYZ');   // NbuException
```

### Курс на дату

```php
Nbu::rate('USD', '2026-03-01');        // 43.2081
Nbu::rate('EUR', now()->subMonth());   // приймає і DateTime
```

### Усі валюти

```php
Nbu::rates();               // ['USD' => Rate, 'EUR' => Rate, ...] - 45 валют
Nbu::rates('2026-03-01');
```

### Конвертація

```php
Nbu::toUah(100, 'USD');         // 4470.06
Nbu::fromUah(4470.06, 'USD');   // 100.0

// Або через сам об'єкт курсу
Nbu::rate('USD')->toUah(100);
```

## Кешування

Банк встановлює один курс на банківський день, тому пакет не питає двічі:

- **сьогоднішній курс** кешується до півночі, коли починається новий
  банківський день;
- **курс за минулу дату** кешується назавжди - опублікований курс уже не
  змінюється.

```php
Nbu::forget('2026-03-01');   // забути конкретний день
Nbu::forget();               // забути резервне значення
```

## Коли банк недоступний

Сторінка з ціною не повинна падати через недоступний bank.gov.ua, тому пакет
віддає останній відомий курс замість винятку. Запитайте, якщо це важливо:

```php
$rate = Nbu::rate('USD');

if (Nbu::isStale()) {
    // Курс із резерву - банк сьогодні не відповів
}
```

`NbuException` кидається лише тоді, коли банк недоступний **і** в кеші нічого
немає.

## Налаштування

```php
// config/nbu.php
'timeout' => 5,              // NBU_TIMEOUT
'cache' => [
    'store' => null,         // NBU_CACHE_STORE, null = типове сховище
    'prefix' => 'nbu',       // NBU_CACHE_PREFIX
    'fallback_days' => 30,   // NBU_FALLBACK_DAYS
],
```

## Тестування

Пакет ходить у мережу звичайним HTTP-клієнтом Laravel, тому підміняється
штатним фейком:

```php
Http::fake(['bank.gov.ua/*' => Http::response([
    ['r030' => 840, 'txt' => 'Долар США', 'rate' => 44.6144, 'cc' => 'USD', 'exchangedate' => '21.08.2026'],
])]);
```

## Вимоги

PHP 8.2+, Laravel 11, 12 або 13.

## Ліцензія

MIT. Деталі у [LICENSE.md](LICENSE.md).
