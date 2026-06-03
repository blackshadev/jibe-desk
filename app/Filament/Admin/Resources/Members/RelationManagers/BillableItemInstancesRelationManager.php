<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Formatters\PriceFormatter;
use App\Models\BillableItemInstance;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->formatStateUsing(static fn (BillPeriod $state) => __('labels.bill_periods.' . $state->value)),
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
                    ->action(static function (BillableItemInstance $record) {
                        $record->stop();
                    })
                    ->successNotificationTitle(__('notifications.billable_item_instance_stopped')),
                Action::make('resume')
                    ->hidden(static fn (BillableItemInstance $record) => !$record->isStopped())
                    ->hiddenLabel()
                    ->icon(Heroicon::PlayCircle)
                    ->requiresConfirmation()
                    ->action(static function (BillableItemInstance $record) {
                        $record->resume();
                    })
                    ->successNotificationTitle(__('notifications.billable_item_instance_resumed')),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.billable_item_instances');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.billable_item_instance'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.billable_item_instances'));
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('labels.all')),
            'active' => Tab::make(__('labels.active'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->active()
                ),
            'inactive' => Tab::make(__('labels.inactive'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->inactive()
                ),
        ];
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
