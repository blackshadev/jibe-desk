<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Observers\PurchaseOrderObserver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property PurchaseOrderStatus $status
 * @property DateTimeInterface $date
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
#[ObservedBy([PurchaseOrderObserver::class])]
final class PurchaseOrder extends Model
{
    use HasFactory;

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'status' => PurchaseOrderStatus::class,
        ];
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
