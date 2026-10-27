<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

final readonly class CreditInvoiceSettlementDb
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
                ->filter(static fn (?CreditInvoicePair $pair): bool => $pair !== null)
                ->unique(static fn (CreditInvoicePair $pair): int => $pair->original->id)
                ->filter(static fn (CreditInvoicePair $pair): bool => $pair->maySettle())
                ->each($this->settle(...));
        });
    }

    private function pairFor(Invoice $invoice): ?CreditInvoicePair
    {
        if ($invoice->creditedInvoice !== null) {
            $original = $invoice->creditedInvoice;

            if (!$this->isPendingOrDeclined($original)) {
                return null;
            }

            return new CreditInvoicePair($invoice, $original);
        }

        $credit = $invoice->creditInvoice;

        if ($credit === null || $credit->status !== InvoiceStatus::Pending) {
            return null;
        }

        return new CreditInvoicePair($credit, $invoice);
    }

    private function isPendingOrDeclined(Invoice $invoice): bool
    {
        return in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Declined], true);
    }

    private function settle(CreditInvoicePair $pair): void
    {
        Invoice::query()
            ->whereIn('id', [$pair->credit->id, $pair->original->id])
            ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Declined])
            ->update([
                'status' => InvoiceStatus::Settled,
                'sepa_batch_id' => null,
                'sepa_sequence_type' => null,
            ]);
    }
}
