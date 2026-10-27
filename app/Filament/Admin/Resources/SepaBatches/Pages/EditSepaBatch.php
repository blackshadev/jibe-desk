<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Pages;

use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Filament\Admin\Resources\SepaBatches\Actions\CloseBatchAction;
use App\Filament\Admin\Resources\SepaBatches\Actions\CompleteBatchAction;
use App\Filament\Admin\Resources\SepaBatches\Actions\ExportSepaAction;
use App\Filament\Admin\Resources\SepaBatches\Actions\MarkSubmittedAction;
use App\Filament\Admin\Resources\SepaBatches\Actions\ReadinessAction;
use App\Filament\Admin\Resources\SepaBatches\Actions\ReplanSequenceAction;
use App\Filament\Admin\Resources\SepaBatches\Actions\RetryDeclined;
use App\Filament\Admin\Resources\SepaBatches\Helpers\OnOpenSepaBatch;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchExportOverridesRelationManager;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchInvoicesRelationManager;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchPurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
use App\Filament\Admin\Resources\SepaBatches\Widgets\BatchStatsOverview;
use App\Filament\Admin\Resources\SepaBatches\Widgets\SepaBatchGenerationProgress;
use App\Models\SepaBatch;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;
use Override;
use Webmozart\Assert\Assert;

final class EditSepaBatch extends EditRecord
{
    #[Override]
    protected static string $resource = SepaBatchResource::class;

    #[Override]
    public function getRelationManagers(): array
    {
        $managers = [
            SepaBatchInvoicesRelationManager::make(),
            SepaBatchPurchaseOrdersRelationManager::make(),
        ];

        $record = $this->getRecord();

        if ($record instanceof SepaBatch && SepaBatchExportOverridesRelationManager::canViewForRecord($record, self::class)) {
            $managers[] = SepaBatchExportOverridesRelationManager::make();
        }

        return $managers;
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        $record = $this->getRecord();
        Assert::isInstanceOf($record, SepaBatch::class);

        return [
            $record->isGenerating()
                ? SepaBatchGenerationProgress::make(['record' => $record])
                : BatchStatsOverview::make(['record' => $record]),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CloseBatchAction::make(),
            ExportSepaAction::make(),
            MarkSubmittedAction::make(),
            CompleteBatchAction::make(),
            ActionGroup::make([
                ReadinessAction::make(),
                ReplanSequenceAction::make(),
                RetryDeclined::make(),
                DeleteAction::make(),
            ])
                ->label(__('labels.more_actions'))
                ->button()
                ->color('gray'),
        ];
    }

    #[Override]
    protected function getFormActions(): array
    {
        $canEdit = static fn (?SepaBatch $record): bool => (
            OnOpenSepaBatch::make($record)
            || $record !== null
            && $record->status === SepaBatchStatus::Pending
            && $record->exported_at === null
        );

        return [
            $this->getSaveFormAction()->visible($canEdit),
            $this->getCancelFormAction()->visible($canEdit),
        ];
    }

    #[Override]
    #[On('refresh')]
    public function refresh(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
