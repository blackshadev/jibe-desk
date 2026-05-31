<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
}
