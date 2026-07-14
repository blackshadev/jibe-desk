<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Override;

final readonly class BankTransactionDbRepository implements BankTransactionRepository
{
    #[Override]
    public function create(CreateBankTransaction $dto): BankTransactionId
    {
        $bankingTransaction = BankingTransaction::query()->create([
            'date' => $dto->date,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'banking_account_number' => $dto->bankingAccountNumber,
            'import_hash' => $dto->importHash,
        ]);

        return BankTransactionId::create($bankingTransaction->id);
    }

    #[Override]
    public function existsByHash(string $hash): bool
    {
        return BankingTransaction::query()->where('import_hash', $hash)->exists();
    }

    #[Override]
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        DB::transaction(static function () use ($bankTransactionId, $invoiceId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->invoices()->syncWithoutDetaching([$invoiceId->value]);

            BookkeepingRecord::query()
                ->where('reference_type', Invoice::class)
                ->where('reference_id', $invoiceId->value)
                ->update(['banking_transaction_id' => $bankTransactionId->value]);
        });
    }

    #[Override]
    public function detachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        DB::transaction(static function () use ($bankTransactionId, $invoiceId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->invoices()->detach($invoiceId->value);

            BookkeepingRecord::query()
                ->where('reference_type', Invoice::class)
                ->where('reference_id', $invoiceId->value)
                ->where('banking_transaction_id', $bankTransactionId->value)
                ->update(['banking_transaction_id' => null]);
        });
    }

    #[Override]
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        DB::transaction(static function () use ($bankTransactionId, $purchaseOrderId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->purchaseOrders()->syncWithoutDetaching([$purchaseOrderId->value]);

            BookkeepingRecord::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $purchaseOrderId->value)
                ->update(['banking_transaction_id' => $bankTransactionId->value]);
        });
    }

    #[Override]
    public function detachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        DB::transaction(static function () use ($bankTransactionId, $purchaseOrderId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->purchaseOrders()->detach($purchaseOrderId->value);

            BookkeepingRecord::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $purchaseOrderId->value)
                ->where('banking_transaction_id', $bankTransactionId->value)
                ->update(['banking_transaction_id' => null]);
        });
    }

    #[Override]
    public function attachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void
    {
        BookkeepingRecord::query()
            ->where('id', $bookkeepingRecordId)
            ->update(['banking_transaction_id' => $bankTransactionId->value]);
    }

    #[Override]
    public function detachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void
    {
        BookkeepingRecord::query()
            ->where('id', $bookkeepingRecordId)
            ->where('banking_transaction_id', $bankTransactionId->value)
            ->update(['banking_transaction_id' => null]);
    }
}
