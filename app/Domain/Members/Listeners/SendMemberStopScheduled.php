<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\MemberStopScheduled;
use App\Domain\Members\Mails\MemberStopped;
use Carbon\CarbonImmutable;

final readonly class SendMemberStopScheduled
{
    public function __construct(
        private MailSender $mailSender,
    ) {}

    public function handle(MemberStopScheduled $event): void
    {
        $this->mailSender->send(new MemberStopped(
            recipient: new Recipient(name: $event->memberName, email: $event->memberEmail),
            stopDate: CarbonImmutable::instance($event->stoppedAt)->format('d-m-Y'),
            isImmediate: $event->isImmediate,
        ));
    }
}
