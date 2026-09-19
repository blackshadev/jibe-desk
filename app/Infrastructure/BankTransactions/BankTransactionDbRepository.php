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
use App\Domain\BankTransactions\ResolveStatus;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Override;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class BankTransactionDbRepository implements BankTransactionRepository
{
    #[Override]
    public function create(CreateBankTransaction $dto): BankTransactionId
    {
        $bankTransaction = BankTransaction::query()->create([
            'date' => $dto->date,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'banking_account_number' => $dto->bankingAccountNumber,
            'bank_account_id' => $dto->bankAccountId,
            'bank_statement_id' => $dto->bankStatementId,
            'import_hash' => $dto->importHash,
        ]);

        return BankTransactionId::create($bankTransaction->id);
    }

    #[Override]
    public function existsByHash(string $hash): bool
    {
        return BankTransaction::query()->where('import_hash', $hash)->exists();
    }

    #[Override]
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        /** @var BankTransaction $bankTransaction */
        $bankTransaction = BankTransaction::query()->findOrFail($bankTransactionId->value);
        $bankTransaction->invoices()->syncWithoutDetaching([$invoiceId->value]);
    }

    #[Override]
    public function detachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        /** @var BankTransaction $bankTransaction */
        $bankTransaction = BankTransaction::query()->findOrFail($bankTransactionId->value);
        $bankTransaction->invoices()->detach($invoiceId->value);
    }

    #[Override]
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        /** @var BankTransaction $bankTransaction */
        $bankTransaction = BankTransaction::query()->findOrFail($bankTransactionId->value);
        $bankTransaction->purchaseOrders()->syncWithoutDetaching([$purchaseOrderId->value]);
    }

    #[Override]
    public function detachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        /** @var BankTransaction $bankTransaction */
        $bankTransaction = BankTransaction::query()->findOrFail($bankTransactionId->value);
        $bankTransaction->purchaseOrders()->detach($purchaseOrderId->value);
    }

    #[Override]
    public function attachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void
    {
        BookkeepingRecord::query()
            ->where('id', $bookkeepingRecordId)
            ->update(['bank_transaction_id' => $bankTransactionId->value]);
    }

    #[Override]
    public function detachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void
    {
        BookkeepingRecord::query()
            ->where('id', $bookkeepingRecordId)
            ->where('bank_transaction_id', $bankTransactionId->value)
            ->update(['bank_transaction_id' => null]);
    }

    #[Override]
    public function getAttachedInvoiceIds(BankTransactionId $bankTransactionId): InvoiceIdList
    {
        $ids = BankTransaction::query()
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
        $ids = BankTransaction::query()
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
            $bt = BankTransaction::query()
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
                    ->update(['bank_transaction_id' => $bankTransactionId->value]);
            }

            $poIds = $bt->purchaseOrders->pluck('id');
            if ($poIds->isNotEmpty()) {
                BookkeepingRecord::query()
                    ->where('reference_type', PurchaseOrder::class)
                    ->whereIn('reference_id', $poIds)
                    ->update(['bank_transaction_id' => $bankTransactionId->value]);
            }
        });
    }

    #[Override]
    public function getUnresolvedIds(int $limit): BankTransactionIdList
    {
        $ids = BankTransaction::query()
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

        return BankTransaction::query()
            ->whereIn('id', $ids->asInts())
            ->get()
            ->mapWithKeys(static fn (BankTransaction $bt): array => [
                $bt->id => new MatchCriteria(
                    date: $bt->date,
                    amount: (float) $bt->unmatched_amount,
                    bankingAccountNumber: $bt->banking_account_number,
                    description: $bt->description,
                    bankAccountId: $bt->bank_account_id,
                ),
            ])
            ->all();
    }

    #[Override]
    public function markAsResolved(BankTransactionId $bankTransactionId): void
    {
        BankTransaction::query()
            ->where('id', $bankTransactionId->value)
            ->update(['resolve_status' => 'resolved']);
    }

    #[Override]
    public function markAsUnresolvable(BankTransactionId $bankTransactionId): void
    {
        BankTransaction::query()
            ->where('id', $bankTransactionId->value)
            ->update(['resolve_status' => 'unresolvable']);
    }

    #[Override]
    public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId
    {
        $date = CarbonImmutable::instance($criteria->date);

        $query = BankTransaction::query()
            ->whereNull('reversed_by_transaction_id')
            ->where('banking_account_number', $criteria->bankingAccountNumber)
            ->where('description', $criteria->description)
            ->whereRaw('ABS(amount + ?) <= 0.01', [$criteria->amount])
            ->whereDate('date', '>=', $date->subDays(56))
            ->whereDate('date', '<=', $date)
            ->orderByRaw('ABS(amount + ?) ASC', [$criteria->amount]);

        if ($criteria->bankAccountId !== null) {
            $query->where('bank_account_id', $criteria->bankAccountId);
        }

        $opposite = $query->first();

        if ($opposite === null) {
            return null;
        }

        return BankTransactionId::create($opposite->id);
    }

    #[Override]
    public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
    {
        DB::transaction(static function () use ($reversalId, $originalId): void {
            BankTransaction::query()
                ->where('id', $reversalId->value)
                ->update([
                    'reversed_by_transaction_id' => $originalId->value,
                ]);

            DB::table('bank_transaction_references')->insertUsing(
                ['bank_transaction_id', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
                DB::table('bank_transaction_references')
                    ->selectRaw('?, reference_type, reference_id, ?, ?', [$reversalId->value, now(), now()])
                    ->where('bank_transaction_id', $originalId->value),
            );

            DB::table('bookkeeping_records')
                ->where('bank_transaction_id', $originalId->value)
                ->update(['bank_transaction_id' => null]);
        });
    }

    #[Override]
    public function unlinkReversal(BankTransactionId $reversalId): void
    {
        DB::transaction(static function () use ($reversalId): void {
            DB::table('bank_transaction_references')
                ->where('bank_transaction_id', $reversalId->value)
                ->delete();

            BankTransaction::query()
                ->where('id', $reversalId->value)
                ->update([
                    'reversed_by_transaction_id' => null,
                ]);
        });
    }

    #[Override]
    public function findInternalTransferMatch(MatchCriteria $criteria): ?BankTransactionId
    {
        if ($criteria->bankAccountId === null) {
            return null;
        }

        $targetAccount = BankAccount::query()
            ->where('iban', BankAccount::normalizeIban($criteria->bankingAccountNumber))
            ->first();

        if ($targetAccount === null || $targetAccount->id === $criteria->bankAccountId) {
            return null;
        }

        $ownIban = BankAccount::query()->whereKey($criteria->bankAccountId)->value('iban');
        if ($ownIban === null) {
            return null;
        }

        $date = CarbonImmutable::instance($criteria->date);

        $counterpart = BankTransaction::query()
            ->where('bank_account_id', $targetAccount->id)
            ->whereRaw("UPPER(REPLACE(banking_account_number, ' ', '')) = ?", [$ownIban])
            ->whereRaw('ABS(amount + ?) <= 0.01', [$criteria->amount])
            ->whereDate('date', '>=', $date->subDays(56))
            ->whereDate('date', '<=', $date->addDays(56))
            ->whereNull('reversed_by_transaction_id')
            ->orderByRaw('ABS(amount + ?) ASC', [$criteria->amount])
            ->first();

        if ($counterpart === null) {
            return null;
        }

        return BankTransactionId::create($counterpart->id);
    }

    #[Override]
    public function linkInternalTransfer(BankTransactionId $a, BankTransactionId $b): void
    {
        if ($a->value === $b->value) {
            return;
        }

        $first = min($a->value, $b->value);
        $second = max($a->value, $b->value);

        DB::transaction(static function () use ($first, $second): void {
            $exists = DB::table('bank_transaction_links')
                ->where('bank_transaction_id', $first)
                ->where('linked_transaction_id', $second)
                ->where('link_type', 'internal_transfer')
                ->exists();

            if (!$exists) {
                DB::table('bank_transaction_links')->insert([
                    'bank_transaction_id' => $first,
                    'linked_transaction_id' => $second,
                    'link_type' => 'internal_transfer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            BankTransaction::query()
                ->whereIn('id', [$first, $second])
                ->update([
                    'status' => BankTransactionStatus::Completed->value,
                    'resolve_status' => ResolveStatus::Resolved->value,
                ]);
        });
    }

    #[Override]
    public function unlinkInternalTransfer(BankTransactionId $id): void
    {
        $link = DB::table('bank_transaction_links')
            ->where('link_type', 'internal_transfer')
            ->where(static fn ($query) => $query
                ->where('bank_transaction_id', $id->value)
                ->orWhere('linked_transaction_id', $id->value))
            ->first();

        if ($link === null) {
            return;
        }

        $otherId = (int) $link->bank_transaction_id === $id->value
            ? (int) $link->linked_transaction_id
            : (int) $link->bank_transaction_id;

        DB::transaction(static function () use ($id, $otherId): void {
            DB::table('bank_transaction_links')
                ->where('link_type', 'internal_transfer')
                ->where(static fn ($query) => $query
                    ->where('bank_transaction_id', $id->value)
                    ->orWhere('linked_transaction_id', $id->value))
                ->delete();

            BankTransaction::query()
                ->whereIn('id', [$id->value, $otherId])
                ->update([
                    'status' => BankTransactionStatus::Open->value,
                    'resolve_status' => ResolveStatus::Unresolved->value,
                ]);
        });
    }

    #[Override]
    public function getLinkedInternalTransferId(BankTransactionId $id): ?BankTransactionId
    {
        $link = DB::table('bank_transaction_links')
            ->where('link_type', 'internal_transfer')
            ->where(static fn ($query) => $query
                ->where('bank_transaction_id', $id->value)
                ->orWhere('linked_transaction_id', $id->value))
            ->first();

        if ($link === null) {
            return null;
        }

        $otherId = (int) $link->bank_transaction_id === $id->value
            ? (int) $link->linked_transaction_id
            : (int) $link->bank_transaction_id;

        return BankTransactionId::create($otherId);
    }
}
