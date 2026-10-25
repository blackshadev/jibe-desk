<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceMailData;
use App\Domain\Billing\Invoices\InvoiceMailLine;
use App\Domain\Billing\Invoices\InvoiceMailRepository;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Mail\Recipient;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Carbon\CarbonImmutable;
use Override;

final readonly class InvoiceMailRepositoryDb implements InvoiceMailRepository
{
    #[Override]
    public function getInvoiceMailData(InvoiceId $id): InvoiceMailData
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->with(['member.paymentInformation', 'lines', 'sepaBatch'])
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
            sepaTransferDate: $invoice->member?->paymentInformation?->mandate_accepted_date !== null ? $invoice->sepaBatch?->sepa_transfer_date : null,
            sentAt: $invoice->sent_at,
        );
    }

    /** @throws InvoicePaymentDeclinedMailUnavailableException */
    #[Override]
    public function getInvoicePaymentDeclinedMailData(InvoiceId $id): InvoicePaymentDeclinedMailData
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::query()
            ->with('lines')
            ->find($id->value);

        if ($invoice === null) {
            throw InvoicePaymentDeclinedMailUnavailableException::invoiceNotFound($id);
        }

        if ($invoice->status !== InvoiceStatus::Declined) {
            throw InvoicePaymentDeclinedMailUnavailableException::invoiceNotDeclined($id);
        }

        if ($invoice->is_credit) {
            throw InvoicePaymentDeclinedMailUnavailableException::invoiceIsCreditInvoice($id);
        }

        if (trim($invoice->recipient_email) === '') {
            throw InvoicePaymentDeclinedMailUnavailableException::recipientEmailMissing($id);
        }

        return new InvoicePaymentDeclinedMailData(
            invoiceId: InvoiceId::create($invoice->id),
            invoiceNumber: $invoice->invoice_number,
            recipient: new Recipient($invoice->recipient_name, $invoice->recipient_email),
            invoiceDate: $invoice->date,
            total: $invoice->total,
            // Plan 04 replaces this literal with the versioned-mandate revoked/expired check.
            mandateNeedsReauthorisation: false,
        );
    }

    #[Override]
    public function markInvoiceAsSent(InvoiceId $id): void
    {
        Invoice::query()
            ->whereKey($id->value)
            ->update(['sent_at' => CarbonImmutable::now()]);
    }
}
