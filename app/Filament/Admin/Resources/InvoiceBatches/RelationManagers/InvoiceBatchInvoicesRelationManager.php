<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\RelationManagers;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Labels\InvoiceStatusLabels;
use App\Filament\Admin\Resources\InvoiceBatches\Helpers\OnPendingInvoice;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Override;

final class InvoiceBatchInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number'))
                    ->searchable(),
                TextColumn::make('recipient_name')
                    ->label(__('labels.recipient_name'))
                    ->searchable(),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state) => InvoiceStatusLabels::options()[$state->value]),
            ])
            ->recordUrl(ViewOrEdit::route(InvoiceResource::class))
            ->recordActions([
                Action::make('markAsPaid')
                    ->label(__('labels.mark_as_paid'))
                    ->icon('heroicon-m-banknotes')
                    ->requiresConfirmation()
                    ->visible(OnPendingInvoice::make(...))
                    ->action(static function (Invoice $record): void {
                        $record->update(['status' => InvoiceStatus::Paid]);
                    })
                    ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refreshInvoicesTable')),

                Action::make('markAsDeclined')
                    ->label(__('labels.mark_as_declined'))
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(OnPendingInvoice::make(...))
                    ->action(static function (Invoice $record): void {
                        $record->update(['status' => InvoiceStatus::Declined]);
                    })
                    ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refreshInvoicesTable')),

                Action::make('removeFromBatch')
                    ->label(__('labels.remove_from_batch'))
                    ->icon('heroicon-m-minus-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(static function (Invoice $record, RelationManager $livewire): bool {
                        /** @var InvoiceBatch $batch */
                        $batch = $livewire->getOwnerRecord();

                        return $batch->status === InvoiceBatchStatus::Open;
                    })
                    ->action(static function (Invoice $record): void {
                        $record->update(['invoice_batch_id' => null]);
                    })
                    ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refreshInvoicesTable')),
            ]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.invoice'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.invoices'));
    }

    #[On('refreshInvoicesTable')]
    public function refresh(): void
    {
    }
}
