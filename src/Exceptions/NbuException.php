<?php

declare(strict_types=1);

namespace AlexBabintsev\Nbu\Exceptions;

use RuntimeException;

/**
 * The bank could not be reached and no cached rates were available to fall
 * back on.
 */
final class NbuException extends RuntimeException
{
    public static function unreachable(string $reason): self
    {
        return new self("Не вдалося отримати курс НБУ: {$reason}");
    }

    public static function unknownCurrency(string $code): self
    {
        return new self("НБУ не публікує курс для валюти «{$code}»");
    }
}
