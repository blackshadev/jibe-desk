<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\BankTransactions\BankTransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * @property int $id
 * @property string $description
 * @property float $amount
 * @property BankTransactionStatus $status
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankingTransaction extends Model
{
    use HasFactory;

    /** @return MorphToMany<Invoice, $this> */
    public function invoices(): MorphToMany
    {
        return $this->morphedByMany(Invoice::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    /** @return MorphToMany<PurchaseOrder, $this> */
    public function purchaseOrders(): MorphToMany
    {
        return $this->morphedByMany(PurchaseOrder::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    /** @return HasMany<BookkeepingRecord, $this> */
    public function bookkeepingRecords(): HasMany
    {
        return $this->hasMany(BookkeepingRecord::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === BankTransactionStatus::Completed;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:3',
            'status' => BankTransactionStatus::class,
        ];
    }

    /**
     * @return Attribute<float, never>
     */
    protected function unmatchedAmount(): Attribute
    {
        return Attribute::get(function (): float {
            $btId = $this->id;

            $invoiceTotal = (float) InvoiceLine::query()
                ->whereIn('invoice_id', static function ($query) use ($btId): void {
                    $query
                        ->select('reference_id')
                        ->from('banking_transaction_references')
                        ->where('banking_transaction_id', $btId)
                        ->where('reference_type', Invoice::class);
                })
                ->sum(DB::raw('price * quantity'));

            $poTotal = (float) PurchaseOrderLine::query()
                ->whereIn('purchase_order_id', static function ($query) use ($btId): void {
                    $query
                        ->select('reference_id')
                        ->from('banking_transaction_references')
                        ->where('banking_transaction_id', $btId)
                        ->where('reference_type', PurchaseOrder::class);
                })
                ->sum(DB::raw('price'));

            return (float) $this->amount - $invoiceTotal + $poTotal;
        });
    }
}
