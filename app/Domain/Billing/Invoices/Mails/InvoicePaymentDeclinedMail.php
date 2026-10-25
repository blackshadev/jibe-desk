<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Mails;

use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
/** @phpstan-ignore domain.dependency */
use App\Models\Invoice;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class InvoicePaymentDeclinedMail extends BaseMail
{
    public function __construct(
        public InvoicePaymentDeclinedMailData $invoice,
        public SepaConfiguration $sepaConfiguration,
    ) {}

    #[Override]
    public function related(): Related
    {
        return new Related(Invoice::class, $this->invoice->invoiceId->value);
    }

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-payment-declined',
            with: [
                'memberName' => $this->invoice->recipient->name,
                'invoiceNumber' => $this->invoice->invoiceNumber,
                'invoiceDate' => $this->invoice->invoiceDate,
                'total' => $this->invoice->total,
                'creditorIban' => $this->sepaConfiguration->creditorIban,
                'creditorAccountName' => $this->sepaConfiguration->creditorName,
                'mandateNeedsReauthorisation' => $this->invoice->mandateNeedsReauthorisation,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Betaling van factuur ' . $this->invoice->invoiceNumber . ' is niet gelukt';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->invoice->recipient;
    }
}
