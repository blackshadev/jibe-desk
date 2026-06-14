<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
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
 */
#[Fillable(['invoice_date', 'status'])]
final class InvoiceBatch extends Model
{
    use HasFactory;

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
            'status' => InvoiceBatchStatus::class,
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
