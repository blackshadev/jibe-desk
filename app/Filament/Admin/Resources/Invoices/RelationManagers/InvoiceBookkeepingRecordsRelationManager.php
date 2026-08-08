<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Filament\Admin\Resources\BookkeepingRecords\BookkeepingRecordResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Override;

final class InvoiceBookkeepingRecordsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'bookkeepingRecords';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.bookkeeping_records');
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year')),
                TextColumn::make('costCenter.title')
                    ->label(__('labels.cost_center')),
                TextColumn::make('description')
                    ->label(__('labels.description')),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR'),
            ])
            ->recordUrl(ViewOrEdit::route(BookkeepingRecordResource::class))
            ->headerActions([])
            ->filters([])
            ->recordActions([]);
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
