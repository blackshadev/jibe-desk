<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

enum SequenceType: string
{
    case First = 'FRST';
    case Recurring = 'RCUR';

    public static function optionallyFrom(?string $value): ?self
    {
        return $value === null ? null : self::from($value);
    }
}
