<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\CouldNotCompleteTransaction;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\BankTransactions\MatchCriteria;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
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

            if (abs($bt->unmatched_amount) >= 0.01) {
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

    #[Override]
    public function getUnresolvedIds(int $limit): BankTransactionIdList
    {
        $ids = BankingTransaction::query()
            ->where('resolve_status', 'unresolved')
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->pluck('id')
            ->map(BankTransactionId::create(...))
            ->all();

        return new BankTransactionIdList($ids);
    }

    #[Override]
    public function getMatchCriteriaForIds(BankTransactionIdList $ids): array
    {
        if ($ids->ids === []) {
            return [];
        }

        return BankingTransaction::query()
            ->whereIn('id', $ids->asInts())
            ->get()
            ->mapWithKeys(static fn (BankingTransaction $bt): array => [
                $bt->id => new MatchCriteria(
                    date: $bt->date,
                    amount: (float) $bt->unmatched_amount,
                    bankingAccountNumber: $bt->banking_account_number,
                    description: $bt->description,
                ),
            ])
            ->all();
    }

    #[Override]
    public function markAsResolved(BankTransactionId $bankTransactionId): void
    {
        BankingTransaction::query()
            ->where('id', $bankTransactionId->value)
            ->update(['resolve_status' => 'resolved']);
    }

    #[Override]
    public function markAsUnresolvable(BankTransactionId $bankTransactionId): void
    {
        BankingTransaction::query()
            ->where('id', $bankTransactionId->value)
            ->update(['resolve_status' => 'unresolvable']);
    }

    #[Override]
    public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId
    {
        $date = CarbonImmutable::instance($criteria->date);

        $opposite = BankingTransaction::query()
            ->whereNull('reversed_by_transaction_id')
            ->where('banking_account_number', $criteria->bankingAccountNumber)
            ->where('description', $criteria->description)
            ->whereRaw('ABS(amount + ?) <= 0.01', [$criteria->amount])
            ->whereDate('date', '>=', $date->subDays(56))
            ->whereDate('date', '<=', $date)
            ->orderByRaw('ABS(amount + ?) ASC', [$criteria->amount])
            ->first();

        if ($opposite === null) {
            return null;
        }

        return BankTransactionId::create($opposite->id);
    }

    #[Override]
    public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
    {
        DB::transaction(static function () use ($reversalId, $originalId): void {
            BankingTransaction::query()
                ->where('id', $reversalId->value)
                ->update([
                    'reversed_by_transaction_id' => $originalId->value,
                ]);

            DB::table('banking_transaction_references')->insertUsing(
                ['banking_transaction_id', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
                DB::table('banking_transaction_references')
                    ->selectRaw('?, reference_type, reference_id, ?, ?', [$reversalId->value, now(), now()])
                    ->where('banking_transaction_id', $originalId->value),
            );

            DB::table('bookkeeping_records')
                ->where('banking_transaction_id', $originalId->value)
                ->update(['banking_transaction_id' => null]);
        });
    }

    #[Override]
    public function unlinkReversal(BankTransactionId $reversalId): void
    {
        DB::transaction(static function () use ($reversalId): void {
            DB::table('banking_transaction_references')
                ->where('banking_transaction_id', $reversalId->value)
                ->delete();

            BankingTransaction::query()
                ->where('id', $reversalId->value)
                ->update([
                    'reversed_by_transaction_id' => null,
                ]);
        });
    }
}
