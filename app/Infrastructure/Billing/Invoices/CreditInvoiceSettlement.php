<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

final readonly class CreditInvoiceSettlement
{
    public function settlePending(InvoiceIdList $ids): void
    {
        if ($ids->ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            $pendingInvoices = Invoice::query()
                ->whereIn('id', $ids->values())
                ->where('status', InvoiceStatus::Pending)
                ->with(['sepaBatch', 'creditedInvoice.sepaBatch', 'creditInvoice.sepaBatch'])
                ->get();

            $pendingInvoices
                ->map($this->pairFor(...))
                ->filter(static fn (?array $pair): bool => $pair !== null)
                ->unique(static fn (array $pair): int => $pair['original']->id)
                ->filter($this->maySettle(...))
                ->each($this->settle(...));
        });
    }

    /** @return array{credit: Invoice, original: Invoice}|null */
    private function pairFor(Invoice $invoice): ?array
    {
        if ($invoice->credit_invoice_id !== null) {
            $original = $invoice->creditedInvoice;

            return $original !== null && $this->isPendingOrDeclined($original)
                ? ['credit' => $invoice, 'original' => $original]
                : null;
        }

        $credit = $invoice->creditInvoice;

        return $credit !== null && $credit->status === InvoiceStatus::Pending
            ? ['credit' => $credit, 'original' => $invoice]
            : null;
    }

    private function isPendingOrDeclined(Invoice $invoice): bool
    {
        return in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Declined], true);
    }

    /** @param array{credit: Invoice, original: Invoice} $pair */
    private function maySettle(array $pair): bool
    {
        if ($pair['original']->status === InvoiceStatus::Declined) {
            return true;
        }

        return !$this->hasMoneyInFlight($pair['credit'])
            && !$this->hasMoneyInFlight($pair['original']);
    }

    private function hasMoneyInFlight(Invoice $invoice): bool
    {
        $batch = $invoice->sepaBatch;

        return $batch !== null && ($batch->exported_at !== null || $batch->submitted_at !== null);
    }

    /** @param array{credit: Invoice, original: Invoice} $pair */
    private function settle(array $pair): void
    {
        Invoice::query()
            ->whereIn('id', [$pair['credit']->id, $pair['original']->id])
            ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Declined])
            ->update([
                'status' => InvoiceStatus::Settled,
                'sepa_batch_id' => null,
                'sepa_sequence_type' => null,
            ]);
    }
}
