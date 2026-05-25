<?php

declare(strict_types=1);

namespace App\Formatters;

final class PriceFormatter
{
    public static function format(float $state): string
    {
        return '€ ' . number_format($state, 2, ',', '.');
    }
}
