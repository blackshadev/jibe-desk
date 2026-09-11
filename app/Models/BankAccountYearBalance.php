<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property int $year
 * @property float $opening_amount
 */
#[Fillable(['bank_account_id', 'year', 'opening_amount'])]
final class BankAccountYearBalance extends Model
{
    use HasFactory;

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opening_amount' => 'decimal:3',
        ];
    }
}
