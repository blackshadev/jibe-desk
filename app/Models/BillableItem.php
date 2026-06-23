<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\Billing\BillableItem as InvoiceBillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillPeriod;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property BillPeriod $bill_period
 * @property int $id
 */
#[Fillable(['description', 'price', 'vat', 'bill_period', 'cost_center_id'])]
final class BillableItem extends Model
{
    use HasFactory;

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public static function createDefault(array $data = []): self
    {
        return self::create([
            'description' => '',
            'price' => 0,
            'vat' => 0,
            'bill_period' => BillPeriod::Annually,
            'cost_center_id' => CostCenter::factory()->create()->id,
            ...$data,
        ]);
    }

    public function toInvoiceBillableItem(): InvoiceBillableItem
    {
        return new InvoiceBillableItem(
            new BillableItemId($this->id),
            $this->compound_price,
            1.0,
            $this->description,
            CostCenterId::create($this->cost_center_id),
        );
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function compoundPrice(): Attribute
    {
        return Attribute::get(
            static fn ($_value, array $attributes): CompoundPrice => new CompoundPrice((float) $attributes['price'], (float) $attributes['vat']),
        );
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'bill_period' => BillPeriod::class,
            'price' => 'decimal:2',
            'vat' => 'decimal:2',
        ];
    }
}
