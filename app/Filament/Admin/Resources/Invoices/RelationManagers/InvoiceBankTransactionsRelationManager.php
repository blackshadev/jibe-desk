<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\Invoices\InvoiceId;
use App\Filament\Admin\Resources\BankTransactions\Actions\AttachBankTransactionAction;
use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use App\Filament\Admin\Resources\BankTransactions\Helpers\IsOpen;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BankTransaction;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Override;

final class InvoiceBankTransactionsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'bankTransactions';

    #[Override]
    protected static ?string $relatedResource = BankTransactionResource::class;

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
            ->recordUrl(ViewOrEdit::route(BankTransactionResource::class))
            ->headerActions([
                AttachBankTransactionAction::make(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('detach')
                    ->label(__('labels.detach'))
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->visible(
                        static fn (RelationManager $livewire, BankTransaction $record): bool => (
                            IsOpen::checkInverse($livewire, $record) && auth()->user()->can('attachBankTransaction', $livewire->getOwnerRecord())
                        ),
                    )
                    ->action(static function (BankTransaction $record, RelationManager $livewire, BankTransactionRepository $repository): void {
                        /** @var Invoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        $repository->detachInvoice(
                            BankTransactionId::create($record->id),
                            InvoiceId::create($invoice->id),
                        );
                    })
                    ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refresh'))
                    ->successNotificationTitle(__('labels.detached')),
            ]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.bank_transaction'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.bank_transactions'));
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
