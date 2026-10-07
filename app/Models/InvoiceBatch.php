<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property InvoiceBatchStatus $status
 * @property DateTimeInterface $invoice_date
 * @property DateTimeInterface $sepa_transfer_date
 * @property CarbonInterface|null $generation_started_at
 * @property CarbonInterface|null $generation_finished_at
 * @property int|null $generation_expected_invoices
 */
#[Fillable([
    'invoice_date',
    'sepa_transfer_date',
    'status',
    'generation_started_at',
    'generation_finished_at',
    'generation_expected_invoices',
])]
final class InvoiceBatch extends Model
{
    use HasFactory;

    public function isGenerating(): bool
    {
        return $this->generation_started_at !== null && $this->generation_finished_at === null;
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'sepa_transfer_date' => 'date',
            'status' => InvoiceBatchStatus::class,
            'generation_started_at' => 'datetime',
            'generation_finished_at' => 'datetime',
            'generation_expected_invoices' => 'integer',
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function total(): Attribute
    {
        return Attribute::get(
            fn () => $this->invoices->reduce(
                static fn (CompoundPrice $total, Invoice $invoice): CompoundPrice => $total->add($invoice->total),
                CompoundPrice::empty(),
            ),
        );
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function openTotal(): Attribute
    {
        return Attribute::get(
            fn () => $this->invoices
                ->filter(static fn (Invoice $invoice) => $invoice->status === InvoiceStatus::Open || $invoice->status === InvoiceStatus::Pending)
                ->reduce(
                    static fn (CompoundPrice $total, Invoice $invoice): CompoundPrice => $total->add($invoice->total),
                    CompoundPrice::empty(),
                ),
        );
    }

    /** @return Attribute<int<0, max>, never> */
    protected function invoiceCount(): Attribute
    {
        return Attribute::get(fn () => $this->invoices()->count());
    }

    /** @return Attribute<int<0, max>, never> */
    protected function openInvoiceCount(): Attribute
    {
        return Attribute::get(fn () => $this->invoices()->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Open])->count());
    }
}
