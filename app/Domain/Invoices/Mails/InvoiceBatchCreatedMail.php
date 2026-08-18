<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Mails;

use App\Domain\Invoices\InvoiceBatchEmailData;
use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
/** @phpstan-ignore domain.dependency */
use App\Filament\Admin\Resources\InvoiceBatches\InvoiceBatchResource;
/** @phpstan-ignore domain.dependency */
use App\Models\InvoiceBatch;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Override;

final readonly class InvoiceBatchCreatedMail extends BaseMail
{
    public function __construct(
        public InvoiceBatchEmailData $batch,
        public Recipient $recipient,
    ) {}

    #[Override]
    public function related(): Related
    {
        return new Related(InvoiceBatch::class, $this->batch->id->value);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nieuwe facturatieronde aangemaakt',
        );
    }

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-batch-created',
            with: [
                'batchId' => $this->batch->id->value,
                'batchDate' => $this->batch->invoiceDate,
                'invoiceCount' => $this->batch->invoiceCount,
                'invoiceTotal' => (string) $this->batch->total,
                'batchUrl' => InvoiceBatchResource::getUrl('edit', ['record' => $this->batch->id->value]),
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Nieuwe facturatieronde aangemaakt';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
