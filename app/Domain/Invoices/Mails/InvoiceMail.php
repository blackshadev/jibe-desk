<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Mails;

use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
/** @phpstan-ignore domain.dependency */
use App\Models\Invoice;
use Illuminate\Mail\Mailables\Content;

final readonly class InvoiceMail extends BaseMail
{
    public function __construct(
        public InvoiceMailData $invoice,
        public SepaConfiguration $sepaConfiguration,
    ) {}

    public function related(): Related
    {
        return new Related(Invoice::class, $this->invoice->invoiceId);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'memberName' => $this->invoice->recipient->name,
                'address' => $this->invoice->recipientAddress,
                'email' => $this->invoice->recipient->email,
                'recipientIban' => $this->invoice->recipientIban,
                'invoiceNumber' => $this->invoice->invoiceNumber,
                'invoiceDate' => $this->invoice->invoiceDate,
                'total' => $this->invoice->total,
                'lines' => $this->invoice->lines,
                'sepaTransferDate' => $this->invoice->sepaTransferDate,
                'creditorIban' => $this->sepaConfiguration->creditorIban,
                'creditorAccountName' => $this->sepaConfiguration->creditorName,
            ],
        );
    }

    public function subject(): string
    {
        return 'Factuur ' . $this->invoice->invoiceNumber;
    }

    public function to(): Recipient
    {
        return $this->invoice->recipient;
    }
}
