<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\RelationManagers;

use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\SepaBatch;
use App\Models\SepaExportOverride;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Override;

final class SepaBatchExportOverridesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'exportOverrides';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof SepaBatch && $ownerRecord->exportOverrides()->exists();
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.export_overrides');
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exported_at')
                    ->label(__('labels.exported_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('invoice.invoice_number')
                    ->label(__('labels.invoice_number'))
                    ->url(static fn (SepaExportOverride $record): string => InvoiceResource::getUrl('view', [
                        'record' => $record->invoice_id,
                    ]))
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label(__('labels.reason'))
                    ->formatStateUsing(static fn (ExportReadinessReason $state): string => __(
                        'labels.export_readiness.reason.' . $state->value,
                    )),
                TextColumn::make('note')
                    ->label(__('labels.note'))
                    ->wrap(),
                TextColumn::make('exportedBy.name')
                    ->label(__('labels.exported_by'))
                    ->placeholder('—'),
            ])
            ->defaultSort('exported_at', 'desc');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.export_override'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.export_overrides'));
    }

    #[Override]
    public static function getBadge(Model $ownerRecord, string $pageClass): string
    {
        return (string) $ownerRecord->{static::$relationship}->count();
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
