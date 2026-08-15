<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\InventoryItem;
use App\Observers\InventoryItemObserver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Override;
use Tests\FeatureTestCase;

final class InventoryItemObserverTest extends FeatureTestCase
{
    private InventoryItemObserver $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->subject = new InventoryItemObserver();
    }

    public function test_it_deletes_the_receipt_file_when_inventory_item_is_deleted(): void
    {
        $path = UploadedFile::fake()->image('receipt.jpg')->store('', 'local');

        $inventoryItem = InventoryItem::factory()->create([
            'receipt_path' => $path,
        ]);

        static::assertTrue(Storage::disk('local')->exists($path));

        $this->subject->deleted($inventoryItem);

        static::assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_it_does_nothing_when_inventory_item_without_receipt_is_deleted(): void
    {
        $inventoryItem = InventoryItem::factory()->create([
            'receipt_path' => null,
        ]);

        $this->subject->deleted($inventoryItem);

        static::assertNull($inventoryItem->receipt_path);
    }
}
