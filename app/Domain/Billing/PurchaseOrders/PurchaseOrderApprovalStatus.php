<?php

declare(strict_types=1);

namespace App\Domain\Billing\PurchaseOrders;

enum PurchaseOrderApprovalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
