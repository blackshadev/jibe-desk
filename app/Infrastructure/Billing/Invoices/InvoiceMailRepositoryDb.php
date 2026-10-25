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
use App\Domain\Billing\Payment\MandateExpiry;
use App\Domain\Billing\Payment\MandateState;
use App\Domain\Mail\Recipient;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Carbon\CarbonImmutable;
use Override;

final readonly class InvoiceMailRepositoryDb implements InvoiceMailRepository
{
    public function __construct(
        private MandateExpiry $mandateExpiry,
    ) {}

    #[Override]
    public function getInvoiceMailData(InvoiceId $id): InvoiceMailData
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->with(['member.paymentInformation', 'lines', 'sepaBatch'])
            ->findOrFail($id->value);

        $mandate = $invoice->member?->paymentInformation;

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
            sepaTransferDate: $mandate !== null ? $invoice->sepaBatch?->sepa_transfer_date : null,
            sentAt: $invoice->sent_at,
        );
    }

    /** @throws InvoicePaymentDeclinedMailUnavailableException */
    #[Override]
    public function getInvoicePaymentDeclinedMailData(InvoiceId $id): InvoicePaymentDeclinedMailData
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::query()
            ->with(['member.paymentInformations', 'lines'])
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

        $mandate = $invoice->member?->paymentInformations->first();

        $mandateNeedsReauthorisation = $mandate !== null
            && $mandate->state($this->mandateExpiry) !== MandateState::Active;

        return new InvoicePaymentDeclinedMailData(
            invoiceId: InvoiceId::create($invoice->id),
            invoiceNumber: $invoice->invoice_number,
            recipient: new Recipient($invoice->recipient_name, $invoice->recipient_email),
            invoiceDate: $invoice->date,
            total: $invoice->total,
            mandateNeedsReauthorisation: $mandateNeedsReauthorisation,
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
