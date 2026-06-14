<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

enum InvoiceBatchStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Completed = 'completed';
}
