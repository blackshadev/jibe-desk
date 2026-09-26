<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Observers\PurchaseOrderObserver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Override;

/**
 * @property PurchaseOrderStatus $status
 * @property DateTimeInterface $date
 * @property ?string $notes
 * @property ?string $declined_reason
 * @property ?int $member_id
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
#[ObservedBy([PurchaseOrderObserver::class])]
final class PurchaseOrder extends Model
{
    use HasFactory;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** @return MorphToMany<BankTransaction, $this> */
    public function bankTransactions(): MorphToMany
    {
        return $this->morphToMany(BankTransaction::class, 'reference', 'bank_transaction_references')
            ->withTimestamps();
    }

    /** @return MorphMany<BookkeepingRecord, $this> */
    public function bookkeepingRecords(): MorphMany
    {
        return $this->morphMany(BookkeepingRecord::class, 'reference');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'status' => PurchaseOrderStatus::class,
            'amount' => 'decimal:3',
        ];
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereIn('status', [PurchaseOrderStatus::Pending]);
    }

    #[Scope]
    protected function orderByRelevancy(Builder $query, float $targetAmount, string $accountNumber): Builder
    {
        return $query->orderByRaw(
            'CASE WHEN creditor_iban = ? THEN 0 ELSE 1 END ASC, ABS(COALESCE((SELECT SUM(price) FROM purchase_order_lines WHERE purchase_order_lines.purchase_order_id = purchase_orders.id), 0) - ?) ASC',
            [$accountNumber, $targetAmount],
        )->orderBy('id', 'asc');
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function displayName(): Attribute
    {
        return Attribute::get(
            fn () => sprintf('[%s] %s - %s', $this->date->format('Y-m-d'), $this->description, $this->total),
        );
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function total(): Attribute
    {
        return Attribute::get(
            fn () => $this->lines->reduce(
                static fn (CompoundPrice $total, PurchaseOrderLine $line): CompoundPrice => $total->add($line->compoundPrice),
                CompoundPrice::empty(),
            ),
        );
    }
}
