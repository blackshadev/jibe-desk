<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Mail\MemberAdministrationRecipient;
use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Members\Listeners\SendAdminNewMemberNotification;
use App\Domain\Members\MemberId;
use App\Domain\Registration\Mails\NewMemberAdminNotification;
use App\Domain\Registration\MembershipData;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendAdminNewMemberNotificationTest extends UnitTestCase
{
    public function test_it_sends(): void
    {
        $mailSender = MailSenderExpectation::create();
        $adminRecipient = new MemberAdministrationRecipient(new Recipient('Admin', 'admin@example.com'));
        $listener = new SendAdminNewMemberNotification($mailSender->mock, $adminRecipient);

        $memberId = MemberId::create(1);
        $name = 'John Doe';
        $membershipData = MembershipData::createDefault();

        $mailSender->expectsSend(new NewMemberAdminNotification(
            $memberId,
            $name,
            $membershipData,
            $adminRecipient->recipient,
        ));

        $listener->handle(new NewMemberRegistration(
            $memberId,
            $name,
            'john@doe.com',
            $membershipData,
        ));
    }
}
