<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

enum SequenceType: string
{
    case First = 'FRST';
    case Recurring = 'RCUR';
}
