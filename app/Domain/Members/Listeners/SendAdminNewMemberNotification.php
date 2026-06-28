<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\MemberAdministrationRecipient;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Registration\Mails\NewMemberAdminNotification;

final readonly class SendAdminNewMemberNotification
{
    public function __construct(
        private MailSender $mailSender,
        private MemberAdministrationRecipient $adminRecipient,
    ) {}

    public function handle(NewMemberRegistration $event): void
    {
        $this->mailSender->send(
            new NewMemberAdminNotification(
                memberId: $event->memberId,
                memberName: $event->memberName,
                membershipData: $event->membershipData,
                recipient: $this->adminRecipient->recipient,
            ),
        );
    }
}
