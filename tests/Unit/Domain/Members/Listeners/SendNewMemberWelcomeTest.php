<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Members\Listeners\SendNewMemberWelcome;
use App\Domain\Members\MemberId;
use App\Domain\Registration\Mails\NewMemberWelcome;
use App\Domain\Registration\MembershipData;
use Override;
use RuntimeException;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\Unit\Domain\Members\MemberPasswordResetLinkExpectation;
use Tests\UnitTestCase;

final class SendNewMemberWelcomeTest extends UnitTestCase
{
    private MailSenderExpectation $mailSender;

    private MemberPasswordResetLinkExpectation $memberPasswordResetLink;

    private SendNewMemberWelcome $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mailSender = MailSenderExpectation::create();
        $this->memberPasswordResetLink = MemberPasswordResetLinkExpectation::create();

        $this->subject = new SendNewMemberWelcome(
            $this->mailSender->mock,
            $this->memberPasswordResetLink->mock,
        );
    }

    public function test_it_sends_welcome_email_with_a_set_password_url(): void
    {
        $setPasswordUrl = 'https://example.test/reset-password/abc?email=jan%40example.com';

        $this->memberPasswordResetLink->expectsGenerate(MemberId::create(1), $setPasswordUrl);

        $this->mailSender->expectsSend(new NewMemberWelcome(
            recipient: new Recipient('Vries, Jan de', 'jan@example.com'),
            setPasswordUrl: $setPasswordUrl,
        ));

        $event = new NewMemberRegistration(
            memberId: MemberId::create(1),
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            membershipData: MembershipData::createDefault(),
        );

        $this->subject->handle($event);
    }

    public function test_it_throws_and_does_not_send_when_the_account_does_not_exist(): void
    {
        $memberId = MemberId::create(99);

        $this->memberPasswordResetLink->expectsGenerateToThrow(
            $memberId,
            new RuntimeException('User not found for member ID: 99'),
        );

        $this->mailSender->expectsNotToSend();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found for member ID: 99');

        $this->subject->handle(new NewMemberRegistration(
            memberId: $memberId,
            memberName: 'Doe, John',
            memberEmail: 'john.doe@example.com',
            membershipData: MembershipData::createDefault(),
        ));
    }
}
