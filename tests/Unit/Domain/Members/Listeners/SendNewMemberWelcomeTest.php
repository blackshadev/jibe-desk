<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Members\Listeners\SendNewMemberWelcome;
use App\Domain\Members\MemberId;
use App\Domain\Registration\Mails\NewMemberWelcome;
use App\Domain\Registration\MembershipData;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendNewMemberWelcomeTest extends UnitTestCase
{
    public function test_it_sends_welcome_email_via_mail_sender(): void
    {
        $mailSender = MailSenderExpectation::create();

        $mailSender->expectsSend(new NewMemberWelcome(new Recipient('Vries, Jan de', 'jan@example.com')));

        $event = new NewMemberRegistration(
            memberId: MemberId::create(1),
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            membershipData: MembershipData::createDefault(),
        );

        $listener = new SendNewMemberWelcome($mailSender->mock);
        $listener->handle($event);
    }
}
