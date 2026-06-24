<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailLine;
use App\Domain\Invoices\InvoiceMailRepository;
use App\Domain\Mail\Recipient;
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
            ->with(['member.paymentInformation', 'lines', 'invoiceBatch'])
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
            recipient: new Recipient($invoice->recipient_name, $invoice->recipient_email),
            recipientIban: $invoice->member?->paymentInformation->iban ?? '',
            recipientAddress: $invoice->recipient_address,
            invoiceDate: $invoice->date,
            total: $invoice->total,
            lines: $lines,
            sepaTransferDate: $invoice->member?->paymentInformation?->mandate_accepted_date !== null ? $invoice->invoiceBatch?->invoice_date : null,
        );
    }
}
