<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property CompoundPrice $amount
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BookkeepingRecord extends Model
{
    use HasFactory;

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<BankingTransaction, $this> */
    public function bankingTransaction(): BelongsTo
    {
        return $this->belongsTo(BankingTransaction::class);
    }

    #[Scope]
    protected function unattached(Builder $query): Builder
    {
        return $query->whereNull('reference_id');
    }

    /** @return Attribute<CompoundPrice, CompoundPrice> */
    public function amount(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value, array $attributes) => new CompoundPrice(
                price: (float) $attributes['amount_price'],
                vat: (float) $attributes['amount_vat'],
            ),
            set: static fn (CompoundPrice $value) => [
                'amount_price' => $value->price,
                'amount_vat' => $value->vat,
            ],
        );
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
