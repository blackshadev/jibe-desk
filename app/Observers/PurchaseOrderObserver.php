<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Storage;

final class PurchaseOrderObserver
{
    public function deleted(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->image_path !== null) {
            Storage::disk('local')->delete($purchaseOrder->image_path);
        }
    }
}
