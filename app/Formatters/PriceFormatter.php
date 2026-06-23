<?php

declare(strict_types=1);

namespace App\Formatters;

use App\Domain\Invoices\CompoundPrice;
use Webmozart\Assert\Assert;

final class PriceFormatter
{
    public static function format(float $state): string
    {
        return '€ ' . number_format($state, 2, ',', '.');
    }

    public static function formatCompound(CompoundPrice $state): string
    {
        return '€ ' . number_format($state->price, 2, ',', '.');
    }

    public static function formatCompoundSignless(?CompoundPrice $state): string
    {
        if (!$state) {
            return '';
        }

        return number_format($state->price, 2, ',', '.');
    }

    public static function parse(string $state): float
    {
        $state = str_replace(
            ['€', ' ', ','],
            ['', '', '.'],
            $state,
        );
        $state = (string) preg_replace('/\.(?=.*\.)/', '', $state);

        Assert::numeric($state);
        return (float) $state;
    }
}
