<?php

declare(strict_types=1);

namespace App\Domain\Mail\Listeners;

use App\Domain\Mail\OutgoingEmailRepository;
use App\Domain\Mail\TrackingId;
use Illuminate\Mail\Events\MessageSent;
use Psr\Clock\ClockInterface;

final readonly class MarkMailAsSent
{
    public function __construct(
        private OutgoingEmailRepository $outgoingEmailRepository,
        private ClockInterface $clock,
    ) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $trackingId = $message->getHeaders()->get('X-Tracking-Id')?->getBodyAsString();

        if ($trackingId === null) {
            return;
        }

        $this->outgoingEmailRepository->markAsSent(
            trackingId: TrackingId::fromString($trackingId),
            messageId: $event->sent->getMessageId(),
            sentAt: $this->clock->now(),
        );
    }
}
