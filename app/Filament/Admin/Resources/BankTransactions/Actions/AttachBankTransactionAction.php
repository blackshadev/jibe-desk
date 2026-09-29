<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use RuntimeException;

final class AttachBankTransactionAction
{
    public static function make(): Action
    {
        return Action::make('attachBankTransaction')
            ->label(__('labels.attach_bank_transaction'))
            ->icon(Heroicon::BuildingLibrary)
            ->modalHeading(__('labels.attach_bank_transaction'))
            ->visible(
                static fn (RelationManager $livewire): bool => auth()->user()->can('attachBankTransaction', $livewire->getOwnerRecord()),
            )
            ->schema([
                Select::make('bank_transaction_id')
                    ->label(__('labels.bank_transaction'))
                    ->options(static function (RelationManager $livewire): Collection {
                        $ownerRecord = $livewire->getOwnerRecord();

                        if ($ownerRecord instanceof Invoice) {
                            $targetAmount = $ownerRecord->total->price;
                            $iban = $ownerRecord->member?->paymentInformation?->banking_account_number;

                            return BankTransaction::query()
                                ->notCompleted()
                                ->whereDoesntHave('invoices', static function ($query) use ($ownerRecord): void {
                                    $query->where('invoices.id', $ownerRecord->id);
                                })
                                ->orderByRelevancy($targetAmount, $iban, $ownerRecord->date)
                                ->get()
                                ->mapWithKeys(static fn (BankTransaction $bt): array => [
                                    $bt->id => $bt->displayName,
                                ]);
                        }

                        if ($ownerRecord instanceof PurchaseOrder) {
                            $targetAmount = -$ownerRecord->total->price;

                            return BankTransaction::query()
                                ->notCompleted()
                                ->whereDoesntHave('purchaseOrders', static function ($query) use ($ownerRecord): void {
                                    $query->where('purchase_orders.id', $ownerRecord->id);
                                })
                                ->orderByRelevancy($targetAmount, $ownerRecord->creditor_iban, $ownerRecord->date)
                                ->get()
                                ->mapWithKeys(static fn (BankTransaction $bt): array => [
                                    $bt->id => $bt->displayName,
                                ]);
                        }

                        throw new RuntimeException('Unsupported owner record type: ' . get_class($ownerRecord));
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, RelationManager $livewire, BankTransactionService $service): void {
                $ownerRecord = $livewire->getOwnerRecord();

                if ($ownerRecord instanceof Invoice) {
                    $service->attachInvoice(
                        BankTransactionId::create((int) $data['bank_transaction_id']),
                        InvoiceId::create($ownerRecord->id),
                    );
                    return;
                }

                if ($ownerRecord instanceof PurchaseOrder) {
                    $service->attachPurchaseOrder(
                        BankTransactionId::create((int) $data['bank_transaction_id']),
                        PurchaseOrderId::create($ownerRecord->id),
                    );
                    return;
                }

                throw new RuntimeException('Unsupported owner record type: ' . get_class($ownerRecord));
            })
            ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refresh'))
            ->successNotificationTitle(__('labels.attached'));
    }
}
