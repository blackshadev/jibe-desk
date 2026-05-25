<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Invoices\Billing\BillPeriod;
use App\Formatters\PriceFormatter;
use App\Models\BillableItemInstance;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class BillableItemInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'billableItemInstances';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('billableItem.bill_period')
                    ->label(__('labels.bill_period'))
                    ->formatStateUsing(fn (BillPeriod $state) => __('labels.bill_periods.' . $state->value)),
                TextColumn::make('billableItem.description')
                    ->label(__('labels.description')),
                TextColumn::make('billableItem.price')
                    ->label(__('labels.price'))
                    ->formatStateUsing(PriceFormatter::format(...)),

                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.end_date'))
                    ->date(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('labels.updated_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->recordActions([
                Action::make('stop')
                    ->hidden(static fn (BillableItemInstance $record) => $record->isStopped())
                    ->hiddenLabel()
                    ->icon(Heroicon::StopCircle)
                    ->requiresConfirmation()
                    ->action(static fn (BillableItemInstance $record) => $record->stop()),
                Action::make('resume')
                    ->hidden(static fn (BillableItemInstance $record) => !$record->isStopped())
                    ->hiddenLabel()
                    ->icon(Heroicon::PlayCircle)
                    ->requiresConfirmation()
                    ->action(static fn (BillableItemInstance $record) => $record->resume())
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.billable_item_instances');
    }

    protected function getDefaultTableSortColumn(): string
    {
        return 'created_at';
    }

    protected function getDefaultTableSortDirection(): string
    {
        return 'desc';
    }
}
