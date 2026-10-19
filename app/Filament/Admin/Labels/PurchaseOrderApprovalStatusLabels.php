<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;

final class PurchaseOrderApprovalStatusLabels
{
    public static function options(): array
    {
        return [
            PurchaseOrderApprovalStatus::Pending->value => __('labels.purchase_order_approval_status.pending'),
            PurchaseOrderApprovalStatus::Accepted->value => __('labels.purchase_order_approval_status.accepted'),
            PurchaseOrderApprovalStatus::Declined->value => __('labels.purchase_order_approval_status.declined'),
        ];
    }
}
