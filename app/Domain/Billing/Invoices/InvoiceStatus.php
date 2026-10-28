<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Declined = 'declined';

    case Settled = 'settled';
}
