<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

enum BillPeriod: string
{
    case Once = 'once';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';

    public function toBillPeriodInMonths(): int
    {
        return match ($this) {
            self::Once => 999,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Annually => 12,
        };
    }
}
