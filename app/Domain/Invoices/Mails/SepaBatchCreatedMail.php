<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Mails;

use App\Domain\Invoices\SepaBatchEmailData;
use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
/** @phpstan-ignore domain.dependency */
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
/** @phpstan-ignore domain.dependency */
use App\Models\SepaBatch;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Override;

final readonly class SepaBatchCreatedMail extends BaseMail
{
    public function __construct(
        public SepaBatchEmailData $batch,
        public Recipient $recipient,
    ) {}

    #[Override]
    public function related(): Related
    {
        return new Related(SepaBatch::class, $this->batch->id->value);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nieuwe SEPA ronde aangemaakt',
        );
    }

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sepa-batch-created',
            with: [
                'batchId' => $this->batch->id->value,
                'batchDate' => $this->batch->invoiceDate,
                'invoiceCount' => $this->batch->invoiceCount,
                'invoiceTotal' => (string) $this->batch->total,
                'batchUrl' => SepaBatchResource::getUrl('edit', ['record' => $this->batch->id->value]),
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Nieuwe SEPA ronde aangemaakt';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
