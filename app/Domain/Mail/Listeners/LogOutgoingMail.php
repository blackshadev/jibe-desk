<?php

declare(strict_types=1);

namespace App\Domain\Mail\Listeners;

use App\Domain\Mail\OutgoingEmail;
use App\Domain\Mail\OutgoingEmailRepository;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\TrackingIdGenerator;
use App\Domain\Members\MemberRepository;
use Illuminate\Mail\Events\MessageSending;
use Psr\Clock\ClockInterface;

final readonly class LogOutgoingMail
{
    public function __construct(
        private OutgoingEmailRepository $outgoingEmailRepository,
        private MemberRepository $memberRepository,
        private ClockInterface $clock,
        private TrackingIdGenerator $trackingIdGenerator,
    ) {}

    public function handle(MessageSending $event): void
    {
        $mailable = $event->message;

        $mailableClass = $mailable
            ->getHeaders()
            ->get('X-Mailable-Class')
            ?->getBodyAsString();

        $to = $event->message->getTo()[0] ?? null;

        if ($to === null || $mailableClass === null) {
            return;
        }

        $recipient = new Recipient($to->getName(), $to->getAddress());
        $trackingId = $this->trackingIdGenerator->generate();
        $mailable->getHeaders()->addTextHeader('X-Tracking-ID', $trackingId->value);

        $memberId = $this->memberRepository->getByEmail($recipient->email);

        $mail = new OutgoingEmail(
            $trackingId,
            $mailableClass,
            $recipient,
            $mailable->getSubject() ?? '',
            $memberId,
            $this->clock->now(),
        );

        $this->outgoingEmailRepository->queue($mail);
    }
}
