<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\SepaBatches;

use App\Filament\Admin\Resources\SepaBatches\Widgets\SepaBatchGenerationProgress;
use App\Models\Invoice;
use App\Models\SepaBatch;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class SepaBatchGenerationProgressTest extends FeatureTestCase
{
    public function test_it_renders_added_count_total_count_and_progress_percent(): void
    {
        $batch = SepaBatch::factory()->generating(12)->create();

        Invoice::factory()
            ->forBatch($batch)
            ->count(4)
            ->createQuietly();

        Livewire::test(SepaBatchGenerationProgress::class, ['record' => $batch])
            ->assertSee(__('labels.generation_in_progress'))
            ->assertSee(__('labels.invoices_added', ['added' => 4, 'total' => 12]))
            ->assertSee('style="width: 33%"', false);
    }
}
