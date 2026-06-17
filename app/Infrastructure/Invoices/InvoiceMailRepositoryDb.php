<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailLine;
use App\Domain\Invoices\InvoiceMailRepository;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Override;

final readonly class InvoiceMailRepositoryDb implements InvoiceMailRepository
{
    #[Override]
    public function getInvoiceMailData(InvoiceId $id): InvoiceMailData
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->with(['member', 'lines', 'invoiceBatch'])
            ->findOrFail($id->value);

        $lines = $invoice->lines->map(
            static fn (InvoiceLine $line) => new InvoiceMailLine(
                description: $line->description,
                quantity: (float) $line->quantity,
                price: $line->compoundPrice,
                subTotal: $line->subTotal,
            ),
        )->all();

        return new InvoiceMailData(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            memberName: $invoice->recipient_name,
            memberEmail: $invoice->member->email ?? '',
            invoiceDate: $invoice->date,
            total: $invoice->total,
            lines: $lines,
            sepaTransferDate: $invoice->invoiceBatch?->invoice_date,
        );
    }
}
