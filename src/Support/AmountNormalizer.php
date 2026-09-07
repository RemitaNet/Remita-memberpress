<?php

declare(strict_types=1);

namespace Remita\Memberpass\Support;

final class AmountNormalizer
{
    public static function toKobo(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromKobo(string|int|float $amount): float
    {
        return round(((float) $amount) / 100, 2);
    }
}
