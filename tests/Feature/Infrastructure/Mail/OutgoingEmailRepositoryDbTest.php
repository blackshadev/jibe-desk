<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Mail;

use App\Domain\Mail\OutgoingEmail;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\TrackingId;
use App\Domain\Members\MemberId;
use App\Infrastructure\Mail\OutgoingEmailRepositoryDb;
use App\Models\Member;
use App\Models\OutgoingEmail as OutgoingEmailModel;
use Carbon\CarbonImmutable;
use Tests\FeatureTestCase;

final class OutgoingEmailRepositoryDbTest extends FeatureTestCase
{
    private OutgoingEmailRepositoryDb $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new OutgoingEmailRepositoryDb();
    }

    public function test_queue_persists_outgoing_email_to_database(): void
    {
        $trackingId = TrackingId::generate();
        $queuedAt = CarbonImmutable::create(2026, 6, 15, 10, 0, 0);

        $email = new OutgoingEmail(
            trackingId: $trackingId,
            mailClass: 'App\Domain\Invoices\Mails\InvoiceMail',
            recipient: new Recipient('Jan de Vries', 'jan@example.com'),
            subject: 'Factuur I-20260001',
            memberId: null,
            queuedAt: $queuedAt,
        );

        $this->repo->queue($email);

        $this->assertDatabaseHas('outgoing_emails', [
            'tracking_id' => $trackingId->value,
            'mailable_class' => 'App\Domain\Invoices\Mails\InvoiceMail',
            'recipient_email' => 'jan@example.com',
            'recipient_name' => 'Jan de Vries',
            'subject' => 'Factuur I-20260001',
            'status' => OutgoingEmailStatus::Queued,
        ]);
    }

    public function test_queue_persists_with_member_id(): void
    {
        $member = Member::factory()->createQuietly();
        $trackingId = TrackingId::generate();

        $email = new OutgoingEmail(
            trackingId: $trackingId,
            mailClass: 'App\Domain\Invoices\Mails\InvoiceMail',
            recipient: new Recipient('Test', 'test@example.com'),
            subject: 'Test',
            memberId: MemberId::create($member->id),
            queuedAt: now(),
        );

        $this->repo->queue($email);

        $this->assertDatabaseHas('outgoing_emails', [
            'tracking_id' => $trackingId->value,
            'member_id' => $member->id,
        ]);
    }

    public function test_mark_as_sent_updates_status_and_message_id(): void
    {
        $trackingId = TrackingId::generate();

        OutgoingEmailModel::query()->create([
            'tracking_id' => $trackingId->value,
            'mailable_class' => 'App\Domain\Invoices\Mails\InvoiceMail',
            'recipient_email' => 'jan@example.com',
            'subject' => 'Test',
            'status' => OutgoingEmailStatus::Queued,
            'queued_at' => now(),
        ]);

        $sentAt = CarbonImmutable::create(2026, 6, 15, 12, 0, 0);
        $this->repo->markAsSent(
            trackingId: $trackingId,
            messageId: 'smtp-id-123',
            sentAt: $sentAt,
        );

        $this->assertDatabaseHas('outgoing_emails', [
            'tracking_id' => $trackingId->value,
            'status' => OutgoingEmailStatus::Sent,
            'message_id' => 'smtp-id-123',
        ]);
    }

    public function test_mark_as_sent_does_not_update_already_sent_email(): void
    {
        $trackingId = TrackingId::generate();

        OutgoingEmailModel::query()->create([
            'tracking_id' => $trackingId->value,
            'mailable_class' => 'App\Domain\Invoices\Mails\InvoiceMail',
            'recipient_email' => 'jan@example.com',
            'subject' => 'Test',
            'status' => OutgoingEmailStatus::Sent,
            'queued_at' => now(),
        ]);

        $this->repo->markAsSent(
            trackingId: $trackingId,
            messageId: 'smtp-id-456',
            sentAt: now(),
        );

        $this->assertDatabaseHas('outgoing_emails', [
            'tracking_id' => $trackingId->value,
            'message_id' => null,
        ]);
    }

    public function test_mark_as_sent_handles_nonexistent_tracking_id(): void
    {
        $this->repo->markAsSent(
            trackingId: TrackingId::generate(),
            messageId: 'smtp-id-789',
            sentAt: now(),
        );

        $this->assertDatabaseMissing('outgoing_emails', [
            'message_id' => 'smtp-id-789',
        ]);
    }
}
