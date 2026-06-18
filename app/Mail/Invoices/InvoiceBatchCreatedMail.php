<?php

declare(strict_types=1);

namespace App\Mail\Invoices;

use App\Domain\Invoices\InvoiceBatchEmailData;
use App\Models\InvoiceBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class InvoiceBatchCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly InvoiceBatchEmailData $batch,
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailable-Class' => self::class,
                'X-Related-Model' => InvoiceBatch::class,
                'X-Related-Id' => $this->batch->id->value,
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nieuwe facturatieronde aangemaakt',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-batch-created',
            with: [
                'batchId' => $this->batch->id->value,
                'batchDate' => $this->batch->invoiceDate,
                'invoiceCount' => $this->batch->invoiceCount,
                'invoiceTotal' => (string) $this->batch->total,
                'batchUrl' => route('filament.admin.resources.invoice-batches.edit', ['record' => $this->batch->id->value]),
            ],
        );
    }
}
