<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Mails;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchEmailData;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\Mails\InvoiceBatchCreatedMail;
use App\Domain\Mail\Recipient;
use App\Models\InvoiceBatch;
use Carbon\CarbonImmutable;
use Tests\FeatureTestCase;

final class InvoiceBatchCreatedMailTest extends FeatureTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $batch = $this->createBatch();
        $recipient = new Recipient('Admin', 'admin@example.com');

        $mail = new InvoiceBatchCreatedMail($batch, $recipient);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_batch_created_message(): void
    {
        $mail = new InvoiceBatchCreatedMail(
            $this->createBatch(),
            new Recipient('Admin', 'admin@example.com'),
        );

        static::assertSame('Nieuwe facturatieronde aangemaakt', $mail->subject());
    }

    public function test_envelope_has_the_batch_created_subject(): void
    {
        $mail = new InvoiceBatchCreatedMail(
            $this->createBatch(),
            new Recipient('Admin', 'admin@example.com'),
        );

        $envelope = $mail->envelope();

        static::assertSame('Nieuwe facturatieronde aangemaakt', $envelope->subject);
    }

    public function test_content_uses_the_batch_created_template_and_passes_the_data(): void
    {
        $batch = $this->createBatch();
        $mail = new InvoiceBatchCreatedMail(
            $batch,
            new Recipient('Admin', 'admin@example.com'),
        );

        $content = $mail->content();

        static::assertSame('mail.invoice-batch-created', $content->markdown);
        static::assertSame($batch->id->value, $content->with['batchId']);
        static::assertSame($batch->invoiceDate, $content->with['batchDate']);
        static::assertSame($batch->invoiceCount, $content->with['invoiceCount']);
        static::assertSame((string) $batch->total, $content->with['invoiceTotal']);
        static::assertArrayHasKey('batchUrl', $content->with);
    }

    public function test_related_points_to_the_invoice_batch(): void
    {
        $batch = $this->createBatch();
        $mail = new InvoiceBatchCreatedMail(
            $batch,
            new Recipient('Admin', 'admin@example.com'),
        );

        $related = $mail->related();

        static::assertSame(InvoiceBatch::class, $related->class);
        static::assertSame($batch->id->value, $related->id);
    }

    private function createBatch(): InvoiceBatchEmailData
    {
        return new InvoiceBatchEmailData(
            id: InvoiceBatchId::create(42),
            invoiceDate: CarbonImmutable::parse('2026-06-15'),
            invoiceCount: 7,
            total: new CompoundPrice(345.50, 72.56),
        );
    }
}
