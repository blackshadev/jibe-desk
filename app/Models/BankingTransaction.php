<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\BankTransactions\BankingTransactionReversalState;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * @property int $id
 * @property string $description
 * @property float $amount
 * @property BankTransactionStatus $status
 * @property ResolveStatus $resolve_status
 * @property DateTimeInterface $date
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankingTransaction extends Model
{
    use HasFactory;

    /** @return MorphToMany<Invoice, $this> */
    public function invoices(): MorphToMany
    {
        return $this->morphedByMany(Invoice::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    /** @return MorphToMany<PurchaseOrder, $this> */
    public function purchaseOrders(): MorphToMany
    {
        return $this->morphedByMany(PurchaseOrder::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    /** @return HasMany<BookkeepingRecord, $this> */
    public function bookkeepingRecords(): HasMany
    {
        return $this->hasMany(BookkeepingRecord::class);
    }

    /** @return BelongsTo<BankingTransaction, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_transaction_id');
    }

    /** @return HasOne<BankingTransaction, $this> */
    public function reversedTransaction(): HasOne
    {
        return $this->hasOne(self::class, 'reversed_by_transaction_id');
    }

    public function reversalState(): BankingTransactionReversalState
    {
        if ($this->isReversal()) {
            return BankingTransactionReversalState::Reversal;
        }

        if ($this->isReversed()) {
            return BankingTransactionReversalState::Reversed;
        }

        return BankingTransactionReversalState::None;
    }

    public function isReversal(): bool
    {
        return $this->reversed_by_transaction_id !== null;
    }

    public function isReversed(): bool
    {
        return $this->reversedTransaction()->exists();
    }

    public function isCompleted(): bool
    {
        return $this->status === BankTransactionStatus::Completed;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'float',
            'status' => BankTransactionStatus::class,
            'resolve_status' => ResolveStatus::class,
            'reversed_by_transaction_id' => 'integer',
        ];
    }

    /**
     * @return Attribute<float, never>
     */
    protected function matchedAmount(): Attribute
    {
        return Attribute::get(function (): float {
            if ($this->isReversal()) {
                return (float) $this->amount;
            }

            $invoiceTotal = (float) InvoiceLine::query()
                ->whereIn('invoice_id', $this->invoices()->select('invoices.id'))
                ->sum(DB::raw('price * quantity'));

            $poTotal = PurchaseOrderLine::query()
                ->whereIn('purchase_order_id', $this->purchaseOrders()->select('purchase_orders.id'))
                ->sum(DB::raw('price'));

            $unattachedBookkeepingRecords = $this
                ->bookkeepingRecords()
                ->unattached()
                ->sum(DB::raw('amount_price'));

            return $invoiceTotal + $unattachedBookkeepingRecords - $poTotal;
        });
    }

    /**
     * @return Attribute<float, never>
     */
    protected function unmatchedAmount(): Attribute
    {
        return Attribute::get(fn (): float => $this->isReversal()
            ? 0.0
            : $this->amount - $this->matched_amount);
    }
}
