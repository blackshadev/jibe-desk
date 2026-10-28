<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Models\Invoice;

final readonly class CreditInvoicePair
{
    public function __construct(
        public Invoice $credit,
        public Invoice $original,
    ) {}

    public function maySettle(): bool
    {
        if ($this->original->status === InvoiceStatus::Declined) {
            return true;
        }

        return !$this->hasMoneyInFlight($this->credit)
            && !$this->hasMoneyInFlight($this->original);
    }

    private function hasMoneyInFlight(Invoice $invoice): bool
    {
        return $invoice->sepaBatch?->exported_at !== null || $invoice->sepaBatch?->submitted_at !== null;
    }
}
