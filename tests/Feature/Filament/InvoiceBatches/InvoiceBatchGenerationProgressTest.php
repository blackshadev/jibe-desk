<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InvoiceBatches;

use App\Filament\Admin\Resources\InvoiceBatches\Widgets\InvoiceBatchGenerationProgress;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class InvoiceBatchGenerationProgressTest extends FeatureTestCase
{
    public function test_it_renders_added_count_total_count_and_progress_percent(): void
    {
        $batch = InvoiceBatch::factory()->generating(12)->create();

        Invoice::factory()
            ->forBatch($batch)
            ->count(4)
            ->createQuietly();

        Livewire::test(InvoiceBatchGenerationProgress::class, ['record' => $batch])
            ->assertSee(__('labels.generation_in_progress'))
            ->assertSee(__('labels.invoices_added', ['added' => 4, 'total' => 12]))
            ->assertSee('style="width: 33%"', false);
    }
}
