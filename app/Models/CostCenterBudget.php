<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['year', 'cost_center_id', 'starting_amount', 'budget_amount'])]
final class CostCenterBudget extends Model
{
    use HasFactory;

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'starting_amount' => 'decimal:3',
            'budget_amount' => 'decimal:3',
        ];
    }
}
