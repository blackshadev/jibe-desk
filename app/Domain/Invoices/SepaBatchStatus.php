<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

enum SepaBatchStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Completed = 'completed';
}
