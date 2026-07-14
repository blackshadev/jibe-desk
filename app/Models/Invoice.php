<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Override;

/**
 * @property InvoiceStatus $status
 * @property DateTimeInterface $date
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class Invoice extends Model
{
    use HasFactory;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<InvoiceBatch, $this> */
    public function invoiceBatch(): BelongsTo
    {
        return $this->belongsTo(InvoiceBatch::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return MorphToMany<BankingTransaction, $this> */
    public function bankingTransactions(): MorphToMany
    {
        return $this->morphToMany(BankingTransaction::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'status' => InvoiceStatus::class,
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function total(): Attribute
    {
        return Attribute::get(
            fn () => $this->lines->reduce(
                static fn (CompoundPrice $total, InvoiceLine $line): CompoundPrice => $total->add($line->subTotal),
                CompoundPrice::empty(),
            ),
        );
    }
}
