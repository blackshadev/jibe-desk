<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use InvalidArgumentException;

enum BillPeriod: string
{
    case Once = 'once';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';

    public function toBillPeriodInMonths(): int
    {
        return match ($this) {
            self::Once => PHP_INT_MAX,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Annually => 12,
        };
    }

    public function toPeriodName(): string
    {
        return match ($this) {
            self::Monthly => 'month',
            self::Quarterly => 'quarter',
            self::Annually => 'year',
            default => throw new InvalidArgumentException('Invalid bill period'),
        };
    }
}
