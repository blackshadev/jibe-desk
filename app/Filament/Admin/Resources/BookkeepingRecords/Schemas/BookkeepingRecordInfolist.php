<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Schemas;

use App\Domain\Invoices\Formatters\PriceFormatter;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BookkeepingRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('year')
                            ->label(__('labels.book_year')),
                        TextEntry::make('costCenter.title')
                            ->label(__('labels.cost_center')),
                        TextEntry::make('description')
                            ->label(__('labels.description')),
                        TextEntry::make('amount')
                            ->label(__('labels.price'))
                            ->formatStateUsing(
                                static fn (?BookkeepingRecord $record): string => PriceFormatter::formatCompoundSignless($record?->amount),
                            ),
                    ]),
                Section::make(__('labels.related_records'))
                    ->schema([
                        TextEntry::make('reference')
                            ->label(__('labels.reference'))
                            ->icon(Heroicon::ArrowTopRightOnSquare)
                            ->state(static fn (BookkeepingRecord $record): string => match (true) {
                                $record->reference instanceof Invoice => $record->reference->display_name,
                                $record->reference instanceof PurchaseOrder => $record->reference->display_name,
                                default => '—',
                            })
                            ->url(static fn (BookkeepingRecord $record): ?string => match (true) {
                                $record->reference instanceof Invoice => ViewOrEdit::routeFor(InvoiceResource::class, $record->reference),
                                $record->reference instanceof PurchaseOrder => ViewOrEdit::routeFor(PurchaseOrderResource::class, $record->reference),
                                default => null,
                            })
                            ->visible(static fn (BookkeepingRecord $record): bool => $record->reference !== null),
                        TextEntry::make('bankingTransaction')
                            ->label(__('labels.banking_transaction'))
                            ->icon(Heroicon::ArrowTopRightOnSquare)
                            ->state(static fn (BookkeepingRecord $record): string => $record->bankingTransaction
                                ? sprintf('[%s] %s', $record->bankingTransaction->date->format('Y-m-d'), $record->bankingTransaction->description)
                                : '—')
                            ->url(static function (BookkeepingRecord $record): ?string {
                                /** @var \App\Models\BankingTransaction|null $bankingTransaction */
                                $bankingTransaction = $record->bankingTransaction;

                                return $bankingTransaction
                                    ? ViewOrEdit::routeFor(BankingTransactionResource::class, $bankingTransaction)
                                    : null;
                            })
                            ->visible(static fn (BookkeepingRecord $record): bool => $record->bankingTransaction !== null),
                    ]),
            ]);
    }
}
