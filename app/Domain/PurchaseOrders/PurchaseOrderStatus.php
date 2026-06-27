<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

enum PurchaseOrderStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
}
