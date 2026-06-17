<?php

declare(strict_types=1);

namespace App\Domain\Mail;

use App\Domain\Members\MemberId;
use DateTimeInterface;

final class OutgoingEmail
{
    public function __construct(
        public TrackingId $trackingId,
        public string $mailClass,
        public Recipient $recipient,
        public string $subject,
        public ?MemberId $memberId,
        public DateTimeInterface $queuedAt,
    ) {}
}
