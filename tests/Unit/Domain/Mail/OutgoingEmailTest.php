<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\OutgoingEmail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\TrackingId;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class OutgoingEmailTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $trackingId = TrackingId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $recipient = new Recipient('Jan de Vries', 'jan@example.com');
        $queuedAt = new CarbonImmutable();

        $email = new OutgoingEmail(
            $trackingId,
            'App\Mail\InvoiceMail',
            $recipient,
            'Test subject',
            MemberId::create(42),
            $queuedAt,
        );

        static::assertSame($trackingId, $email->trackingId);
        static::assertSame('App\Mail\InvoiceMail', $email->mailClass);
        static::assertSame($recipient, $email->recipient);
        static::assertSame('Test subject', $email->subject);
        static::assertEquals(MemberId::create(42), $email->memberId);
        static::assertSame($queuedAt, $email->queuedAt);
    }

    public function test_it_allows_null_member_id(): void
    {
        $email = new OutgoingEmail(
            TrackingId::generate(),
            'App\Mail\InvoiceMail',
            new Recipient('Jan de Vries', 'jan@example.com'),
            'Test subject',
            null,
            new CarbonImmutable(),
        );

        static::assertNull($email->memberId);
    }
}
