<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Mails;

use App\Domain\Invoices\Mails\SepaBatchCreatedMail;
use App\Domain\Invoices\SepaBatchEmailData;
use App\Domain\Invoices\SepaBatchId;
use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use App\Models\SepaBatch;
use Carbon\CarbonImmutable;
use Tests\FeatureTestCase;

final class SepaBatchCreatedMailTest extends FeatureTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $batch = $this->createBatch();
        $recipient = new Recipient('Admin', 'admin@example.com');

        $mail = new SepaBatchCreatedMail($batch, $recipient);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_batch_created_message(): void
    {
        $mail = new SepaBatchCreatedMail(
            $this->createBatch(),
            new Recipient('Admin', 'admin@example.com'),
        );

        static::assertSame('Nieuwe SEPA ronde aangemaakt', $mail->subject());
    }

    public function test_envelope_has_the_batch_created_subject(): void
    {
        $mail = new SepaBatchCreatedMail(
            $this->createBatch(),
            new Recipient('Admin', 'admin@example.com'),
        );

        $envelope = $mail->envelope();

        static::assertSame('Nieuwe SEPA ronde aangemaakt', $envelope->subject);
    }

    public function test_content_uses_the_batch_created_template_and_passes_the_data(): void
    {
        $batch = $this->createBatch();
        $mail = new SepaBatchCreatedMail(
            $batch,
            new Recipient('Admin', 'admin@example.com'),
        );

        $content = $mail->content();

        static::assertSame('mail.sepa-batch-created', $content->markdown);
        static::assertSame($batch->id->value, $content->with['batchId']);
        static::assertSame($batch->invoiceDate, $content->with['batchDate']);
        static::assertSame($batch->invoiceCount, $content->with['invoiceCount']);
        static::assertSame((string) $batch->total, $content->with['invoiceTotal']);
        static::assertArrayHasKey('batchUrl', $content->with);
    }

    public function test_related_points_to_the_sepa_batch(): void
    {
        $batch = $this->createBatch();
        $mail = new SepaBatchCreatedMail(
            $batch,
            new Recipient('Admin', 'admin@example.com'),
        );

        $related = $mail->related();

        static::assertSame(SepaBatch::class, $related->class);
        static::assertSame($batch->id->value, $related->id);
    }

    private function createBatch(): SepaBatchEmailData
    {
        return new SepaBatchEmailData(
            id: SepaBatchId::create(42),
            invoiceDate: CarbonImmutable::parse('2026-06-15'),
            invoiceCount: 7,
            total: new CompoundPrice(345.50, 72.56),
        );
    }
}
