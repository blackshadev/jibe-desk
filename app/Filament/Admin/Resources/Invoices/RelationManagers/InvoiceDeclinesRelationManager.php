<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use App\Models\InvoiceDecline;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class InvoiceDeclinesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'declines';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.decline_history');
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('declined_at')
                    ->label(__('labels.declined_at'))
                    ->dateTime(),
                TextColumn::make('sepaBatch.invoice_date')
                    ->label(__('labels.source_batch'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('bankTransaction.id')
                    ->label(__('labels.reversal_transaction'))
                    ->formatStateUsing(static fn (?int $state): string => $state === null ? '—' : '#' . $state)
                    ->url(static fn (InvoiceDecline $record): ?string => $record->bank_transaction_id === null
                        ? null
                        : BankTransactionResource::getUrl('view', ['record' => $record->bank_transaction_id])),
                TextColumn::make('note')
                    ->label(__('labels.decline_note'))
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([]);
    }
}
