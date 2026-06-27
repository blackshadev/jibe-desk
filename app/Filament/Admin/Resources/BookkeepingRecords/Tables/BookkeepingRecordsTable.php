<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Tables;

use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class BookkeepingRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year'))
                    ->sortable(),
                TextColumn::make('costCenter.title')
                    ->label(__('labels.cost_center'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('labels.book_year'))
                    ->options(
                        static fn () => BookkeepingRecord::query()
                            ->select('year')
                            ->distinct()
                            ->pluck('year', 'year')
                            ->toArray(),
                    ),
                SelectFilter::make('cost_center_id')
                    ->label(__('labels.cost_center'))
                    ->relationship('costCenter', 'title'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('related')
                    ->label(__('labels.goto_related'))
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->url(static fn (BookkeepingRecord $record): string => match (get_class($record->reference)) {
                        Invoice::class => ViewOrEdit::routeFor(InvoiceResource::class, $record->reference),
                        PurchaseOrder::class => ViewOrEdit::routeFor(PurchaseOrderResource::class, $record->reference),
                        default => '',
                    })
                    ->visible(static fn (BookkeepingRecord $record): bool => $record->reference !== null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
