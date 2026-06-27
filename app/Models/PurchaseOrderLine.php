<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['description', 'price', 'price_vat', 'cost_center_id'])]
final class PurchaseOrderLine extends Model
{
    use HasFactory;

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'price_vat' => 'decimal:3',
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function compoundPrice(): Attribute
    {
        return Attribute::get(
            static fn ($_value, array $attributes) => new CompoundPrice(
                (float) ($attributes['price'] ?? 0.0),
                (float) ($attributes['price_vat'] ?? 0.0),
            ),
        );
    }
}
