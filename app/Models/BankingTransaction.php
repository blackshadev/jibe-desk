<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Override;

/**
 * @property int $id
 * @property string $description
 * @property float $amount
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

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:3',
        ];
    }

    /**
     * @return Attribute<float, never>
     */
    protected function unmatchedAmount(): Attribute
    {
        return Attribute::get(function (): float {
            $recordsSum = $this->bookkeepingRecords->sum('amount_price');

            return (float) $this->amount - $recordsSum;
        });
    }
}
