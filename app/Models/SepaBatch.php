<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property SepaBatchStatus $status
 * @property DateTimeInterface $invoice_date
 * @property DateTimeInterface $sepa_transfer_date
 * @property CarbonInterface|null $generation_started_at
 * @property CarbonInterface|null $generation_finished_at
 * @property int|null $generation_expected_invoices
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $exported_at
 * @property CarbonInterface|null $submitted_at
 * @property int|null $submitted_by
 */
#[Fillable([
    'invoice_date',
    'sepa_transfer_date',
    'status',
    'generation_started_at',
    'generation_finished_at',
    'generation_expected_invoices',
    'closed_at',
    'exported_at',
    'submitted_at',
    'submitted_by',
])]
final class SepaBatch extends Model
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

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasMany<SepaExportOverride, $this> */
    public function exportOverrides(): HasMany
    {
        return $this->hasMany(SepaExportOverride::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'sepa_transfer_date' => 'date',
            'status' => SepaBatchStatus::class,
            'generation_started_at' => 'datetime',
            'generation_finished_at' => 'datetime',
            'generation_expected_invoices' => 'integer',
            'closed_at' => 'datetime',
            'exported_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function total(): Attribute
    {
        return Attribute::get(
            fn () => $this->invoices->reduce(
                static fn (CompoundPrice $total, Invoice $invoice): CompoundPrice => $total->add($invoice->total),
                CompoundPrice::empty(),
            )->sub($this->purchaseOrders->reduce(
                static fn (CompoundPrice $total, PurchaseOrder $purchaseOrder): CompoundPrice => $total->add($purchaseOrder->total),
                CompoundPrice::empty(),
            )),
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
                )
                ->add(
                    $this->purchaseOrders
                        ->filter(static fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->status === PurchaseOrderStatus::Pending)
                        ->reduce(
                            static fn (CompoundPrice $total, PurchaseOrder $purchaseOrder): CompoundPrice => $total->add($purchaseOrder->total),
                            CompoundPrice::empty(),
                        )
                        ->abs(),
                ),
        );
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function openInvoiceTotal(): Attribute
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

    /** @return Attribute<CompoundPrice, never> */
    protected function openPurchaseOrderTotal(): Attribute
    {
        return Attribute::get(
            fn () => $this->purchaseOrders
                ->filter(static fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->status === PurchaseOrderStatus::Pending)
                ->reduce(
                    static fn (CompoundPrice $total, PurchaseOrder $purchaseOrder): CompoundPrice => $total->add($purchaseOrder->total),
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
    protected function purchaseOrderCount(): Attribute
    {
        return Attribute::get(fn () => $this->purchaseOrders()->count());
    }

    /** @return Attribute<int<0, max>, never> */
    protected function openInvoiceCount(): Attribute
    {
        return Attribute::get(fn () => $this->invoices()->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Open])->count());
    }
}
