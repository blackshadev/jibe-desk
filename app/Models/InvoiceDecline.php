<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $invoice_id
 * @property int|null $sepa_batch_id
 * @property int|null $bank_transaction_id
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class InvoiceDecline extends Model
{
    use HasFactory;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<SepaBatch, $this> */
    public function sepaBatch(): BelongsTo
    {
        return $this->belongsTo(SepaBatch::class);
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    #[Override]
    protected function casts(): array
    {
        return ['declined_at' => 'datetime'];
    }
}
