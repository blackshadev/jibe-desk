<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'title', 'description'])]
final class CostCenter extends Model
{
    use HasFactory;

    /** @return HasMany<BillableItem, $this> */
    public function billableItems(): HasMany
    {
        return $this->hasMany(BillableItem::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<CostCenterBudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(CostCenterBudget::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }
}
