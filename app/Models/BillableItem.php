<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Domain\Invoices\CompoundPrice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property BillPeriod $bill_period */
#[Fillable(['description', 'price', 'vat', 'bill_period'])]
final class BillableItem extends Model
{
    use HasFactory;

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function compoundPrice(): Attribute
    {
        return Attribute::get(
            static fn ($value, array $attributes): CompoundPrice => new CompoundPrice((float) $attributes['price'], (float) $attributes['vat'])
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bill_period' => BillPeriod::class,
            'price' => 'decimal:2',
            'vat' => 'decimal:2',
        ];
    }
}
