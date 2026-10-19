<?php

declare(strict_types=1);

namespace App\Domain\Billing\PurchaseOrders;

enum PurchaseOrderStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
