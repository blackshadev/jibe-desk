<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\OutgoingEmail as OutgoingEmailEntity;
use App\Domain\Mail\OutgoingEmailRepository;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Domain\Mail\TrackingId;
use App\Models\OutgoingEmail;
use DateTimeInterface;
use Override;

final class OutgoingEmailRepositoryDb implements OutgoingEmailRepository
{
    #[Override]
    public function queue(
        OutgoingEmailEntity $outgoingEmail,
    ): void {
        OutgoingEmail::create([
            'tracking_id' => $outgoingEmail->trackingId->value,
            'mailable_class' => $outgoingEmail->mailClass,
            'subject' => $outgoingEmail->subject,
            'recipient_email' => $outgoingEmail->recipient->email,
            'recipient_name' => $outgoingEmail->recipient->name,
            'member_id' => $outgoingEmail->memberId?->value,
            'queued_at' => $outgoingEmail->queuedAt,
            'status' => OutgoingEmailStatus::Queued,
        ]);
    }

    #[Override]
    public function markAsSent(
        TrackingId $trackingId,
        string $messageId,
        DateTimeInterface $sentAt,
    ): void {
        OutgoingEmail::query()
            ->where('tracking_id', $trackingId->value)
            ->where('status', OutgoingEmailStatus::Queued)
            ->update([
                'status' => OutgoingEmailStatus::Sent,
                'message_id' => $messageId,
                'sent_at' => $sentAt,
            ]);
    }
}
