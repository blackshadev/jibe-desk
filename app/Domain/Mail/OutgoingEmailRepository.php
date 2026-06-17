<?php

declare(strict_types=1);

namespace App\Domain\Mail;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface OutgoingEmailRepository
{
    public function queue(OutgoingEmail $outgoingEmail): void;

    public function markAsSent(
        TrackingId $trackingId,
        string $messageId,
        DateTimeInterface $sentAt,
    ): void;
}
