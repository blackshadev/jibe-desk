<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Listeners\SendRegistrationReceived;
use App\Domain\Members\MemberId;
use App\Domain\Registration\Events\NewMemberRegistration;
use App\Domain\Registration\Mails\RegistrationReceived;
use App\Domain\Registration\MembershipData;
use App\Domain\Registration\RegistrationSource;
use Override;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendRegistrationReceivedTest extends UnitTestCase
{
    private MailSenderExpectation $mailSender;

    private SendRegistrationReceived $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mailSender = MailSenderExpectation::create();

        $this->subject = new SendRegistrationReceived(
            $this->mailSender->mock,
        );
    }

    public function test_it_sends_registration_received_email(): void
    {
        $this->mailSender->expectsSend(new RegistrationReceived(
            new Recipient(name: 'Vries, Jan de', email: 'jan@example.com'),
        ));

        $this->subject->handle(new NewMemberRegistration(
            memberId: MemberId::create(1),
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            membershipData: MembershipData::createDefault(),
            source: RegistrationSource::RegistrationForm,
        ));
    }
}
