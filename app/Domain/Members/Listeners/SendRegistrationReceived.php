<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Registration\Events\NewMemberRegistration;
use App\Domain\Registration\Mails\RegistrationReceived;

final readonly class SendRegistrationReceived
{
    public function __construct(
        private MailSender $mailSender,
    ) {}

    public function handle(NewMemberRegistration $event): void
    {
        $this->mailSender->send(new RegistrationReceived(
            new Recipient(name: $event->memberName, email: $event->memberEmail),
        ));
    }
}
