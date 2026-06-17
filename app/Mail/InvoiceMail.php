<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Invoices\InvoiceMailData;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly InvoiceMailData $invoice,
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailable-Class' => self::class,
                'X-Related-Model' => Invoice::class,
                'X-Related-Id' => $this->invoice->invoiceId,
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factuur ' . $this->invoice->invoiceNumber,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'memberName' => $this->invoice->memberName,
                'invoiceNumber' => $this->invoice->invoiceNumber,
                'invoiceDate' => $this->invoice->invoiceDate,
                'total' => (string) $this->invoice->total,
                'lines' => $this->invoice->lines,
                'sepaTransferDate' => $this->invoice->sepaTransferDate,
            ],
        );
    }
}
