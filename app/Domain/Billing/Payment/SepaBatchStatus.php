<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

enum SepaBatchStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Completed = 'completed';
}
