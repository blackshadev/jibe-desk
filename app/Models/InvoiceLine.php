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

#[Fillable(['description', 'vat', 'price', 'quantity', 'member_id', 'billable_item_id'])]
final class InvoiceLine extends Model
{
    use HasFactory;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'vat' => 'decimal:3',
            'price' => 'decimal:3',
            'quantity' => 'decimal:2',
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function _compoundPrice(): Attribute
    {
        return Attribute::get(static fn ($_value, array $attributes) => new CompoundPrice($attributes['price'], $attributes['vat']));
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function _subTotal(): Attribute
    {
        return Attribute::get(static fn (mixed $_value, array $attributes) => new CompoundPrice(
            $attributes['price'] * $attributes['quantity'],
            $attributes['vat'] * $attributes['quantity'],
        ));
    }
}
