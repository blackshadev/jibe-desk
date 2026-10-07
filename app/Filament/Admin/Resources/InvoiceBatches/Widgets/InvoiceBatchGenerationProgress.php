<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Widgets;

use App\Models\InvoiceBatch;
use Carbon\Constants\DiffOptions;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Override;

final class InvoiceBatchGenerationProgress extends Widget
{
    use CanPoll;

    public ?InvoiceBatch $record = null;

    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected string $view = 'filament.admin.resources.invoice-batches.widgets.invoice-batch-generation-progress';

    #[Override]
    protected function getViewData(): array
    {
        $record = $this->record;

        if ($record !== null && !$record->isGenerating()) {
            $this->js('window.location.reload()');
        }

        $total = $record === null ? 0 : $record->generation_expected_invoices ?? 0;
        $added = $record !== null ? $record->invoice_count : 0;

        return [
            'addedCount' => $added,
            'totalCount' => $total,
            'progressPercent' => $total > 0 ? max(0, min(100, (int) round(($added / $total) * 100))) : 0,
            'duration' => $record?->generation_started_at?->diffForHumans(now(), DiffOptions::DIFF_ABSOLUTE),
        ];
    }
}
