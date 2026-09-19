<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property string $statement_number
 * @property DateTimeInterface $start_date
 * @property DateTimeInterface $end_date
 * @property float $opening_balance
 * @property float $closing_balance
 * @property string $currency
 * @property string $file_path
 * @property StatementIntegrityStatus $integrity_status
 * @property StatementChainStatus $chain_status
 * @property float $balance_difference
 * @property-read float|null $matched_percentage
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankStatement extends Model
{
    use HasFactory;

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    protected function matchedPercentage(): Attribute
    {
        return Attribute::get(function (): ?float {
            $transactions = $this->transactions;

            if ($transactions->isEmpty()) {
                return null;
            }

            $totalAmount = $transactions->sum(static fn (BankTransaction $transaction): float => abs($transaction->amount));

            if ($totalAmount < 0.01) {
                return null;
            }

            $totalMatched = $transactions->sum(static fn (BankTransaction $transaction): float => abs($transaction->matched_amount));

            return round(($totalMatched / $totalAmount) * 100, 2);
        });
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'opening_balance' => 'decimal:3',
            'closing_balance' => 'decimal:3',
            'balance_difference' => 'decimal:3',
            'integrity_status' => StatementIntegrityStatus::class,
            'chain_status' => StatementChainStatus::class,
        ];
    }
}
