<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\PurchaseOrder;
use App\Observers\PurchaseOrderObserver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Override;
use Tests\FeatureTestCase;

final class PurchaseOrderObserverTest extends FeatureTestCase
{
    private PurchaseOrderObserver $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->subject = new PurchaseOrderObserver();
    }

    public function testItDeletesTheImageFileWhenPurchaseOrderIsDeleted(): void
    {
        $path = UploadedFile::fake()->image('invoice.jpg')->store('', 'local');

        $purchaseOrder = PurchaseOrder::factory()->create([
            'image_path' => $path,
        ]);

        static::assertTrue(Storage::disk('local')->exists($path));

        $this->subject->deleted($purchaseOrder);

        static::assertFalse(Storage::disk('local')->exists($path));
    }

    public function testItDoesNothingWhenPurchaseOrderWithoutImageIsDeleted(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'image_path' => null,
        ]);

        $this->subject->deleted($purchaseOrder);

        static::assertNull($purchaseOrder->image_path);
    }
}
