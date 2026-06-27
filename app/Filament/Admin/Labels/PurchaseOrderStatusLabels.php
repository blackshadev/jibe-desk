<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;

final class PurchaseOrderStatusLabels
{
    public static function options(): array
    {
        return [
            PurchaseOrderStatus::Open->value => __('labels.purchase_order_status.open'),
            PurchaseOrderStatus::Pending->value => __('labels.purchase_order_status.pending'),
            PurchaseOrderStatus::Paid->value => __('labels.purchase_order_status.paid'),
        ];
    }
}
