<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Override;

final class InvoiceBankingTransactionsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'bankingTransactions';

    #[Override]
    protected static ?string $relatedResource = BankingTransactionResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('labels.description')),
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('labels.total'))
                    ->money('EUR')
                    ->alignEnd(),
            ])
            ->recordUrl(ViewOrEdit::route(BankingTransactionResource::class))
            ->headerActions([])
            ->filters([])
            ->recordActions([]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.banking_transaction'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.banking_transactions'));
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
