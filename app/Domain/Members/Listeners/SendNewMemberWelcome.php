<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Members\MemberPasswordResetLink;
use App\Domain\Registration\Events\NewMemberRegistration;
use App\Domain\Registration\Mails\NewMemberWelcome;

final readonly class SendNewMemberWelcome
{
    public function __construct(
        private MailSender $mailSender,
        private MemberPasswordResetLink $memberPasswordResetLink,
    ) {}

    public function handle(NewMemberRegistration $event): void
    {
        $setPasswordUrl = $this->memberPasswordResetLink->generate($event->memberId);

        $this->mailSender->send(new NewMemberWelcome(
            new Recipient(name: $event->memberName, email: $event->memberEmail),
            setPasswordUrl: $setPasswordUrl,
        ));
    }
}
