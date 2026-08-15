<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\Storage;

final class InventoryItemObserver
{
    public function deleted(InventoryItem $inventoryItem): void
    {
        if ($inventoryItem->receipt_path !== null) {
            Storage::disk('local')->delete($inventoryItem->receipt_path);
        }
    }
}
