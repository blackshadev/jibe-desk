<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\InventoryItemObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property float $residual_value
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
#[ObservedBy([InventoryItemObserver::class])]
final class InventoryItem extends Model
{
    use HasFactory;

    /** @return BelongsTo<InventoryCategory, $this> */
    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'write_off_period_years' => 'integer',
        ];
    }

    protected function residualValue(): Attribute
    {
        return Attribute::get(static function (mixed $_value, array $attributes): float {
           $years = abs(ceil(CarbonImmutable::make($attributes['date'])->diffInYears(now())));
           $writeOffPeriodYears = $attributes['write_off_period_years'];
           if ($years >= $writeOffPeriodYears) {
               return 0;
           }

           $fractionalValue = ($writeOffPeriodYears - $years) / $writeOffPeriodYears;

            return $attributes['amount'] * $fractionalValue;
        });
    }

}
