<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\CouldNotCompleteTransaction;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
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
        /** @var BankingTransaction $bankingTransaction */
        $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
        $bankingTransaction->invoices()->syncWithoutDetaching([$invoiceId->value]);
    }

    #[Override]
    public function detachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        /** @var BankingTransaction $bankingTransaction */
        $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
        $bankingTransaction->invoices()->detach($invoiceId->value);
    }

    #[Override]
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        /** @var BankingTransaction $bankingTransaction */
        $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
        $bankingTransaction->purchaseOrders()->syncWithoutDetaching([$purchaseOrderId->value]);
    }

    #[Override]
    public function detachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        /** @var BankingTransaction $bankingTransaction */
        $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
        $bankingTransaction->purchaseOrders()->detach($purchaseOrderId->value);
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

    #[Override]
    public function getAttachedInvoiceIds(BankTransactionId $bankTransactionId): InvoiceIdList
    {
        $ids = BankingTransaction::query()
            ->findOrFail($bankTransactionId->value)
            ->invoices()
            ->pluck('reference_id')
            ->map(InvoiceId::create(...))
            ->all();

        return new InvoiceIdList($ids);
    }

    #[Override]
    public function getAttachedPurchaseOrderIds(BankTransactionId $bankTransactionId): PurchaseOrderIdList
    {
        $ids = BankingTransaction::query()
            ->findOrFail($bankTransactionId->value)
            ->purchaseOrders()
            ->pluck('reference_id')
            ->map(PurchaseOrderId::create(...))
            ->all();

        return new PurchaseOrderIdList($ids);
    }

    #[Override]
    public function complete(BankTransactionId $bankTransactionId): void
    {
        DB::transaction(static function () use ($bankTransactionId): void {
            $bt = BankingTransaction::query()
                ->with(['invoices.lines', 'purchaseOrders.lines'])
                ->findOrFail($bankTransactionId->value);

            $invoiceTotal = $bt->invoices->sum(static fn (Invoice $i) => (float) $i->total->price);
            $poTotal = $bt->purchaseOrders->sum(static fn (PurchaseOrder $po) => (float) $po->total->price);
            $unmatched = (float) $bt->amount - $invoiceTotal + $poTotal;

            if (abs($unmatched) >= 0.01) {
                throw new CouldNotCompleteTransaction();
            }

            $bt->update(['status' => BankTransactionStatus::Completed]);

            $invoiceIds = $bt->invoices->pluck('id');
            if ($invoiceIds->isNotEmpty()) {
                BookkeepingRecord::query()
                    ->where('reference_type', Invoice::class)
                    ->whereIn('reference_id', $invoiceIds)
                    ->update(['banking_transaction_id' => $bankTransactionId->value]);
            }

            $poIds = $bt->purchaseOrders->pluck('id');
            if ($poIds->isNotEmpty()) {
                BookkeepingRecord::query()
                    ->where('reference_type', PurchaseOrder::class)
                    ->whereIn('reference_id', $poIds)
                    ->update(['banking_transaction_id' => $bankTransactionId->value]);
            }
        });
    }
}
