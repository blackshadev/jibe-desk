<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\MemberAccountProvisioned;
use App\Domain\Members\Listeners\SendMemberWelcome;
use App\Domain\Members\Mails\MemberWelcome;
use App\Domain\Members\MemberId;
use Filament\Facades\Filament;
use Override;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\Unit\Domain\Members\MemberPasswordResetLinkExpectation;
use Tests\UnitTestCase;

final class SendMemberWelcomeTest extends UnitTestCase
{
    private MailSenderExpectation $mailSender;

    private MemberPasswordResetLinkExpectation $memberPasswordResetLink;

    private SendMemberWelcome $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mailSender = MailSenderExpectation::create();
        $this->memberPasswordResetLink = MemberPasswordResetLinkExpectation::create();

        $this->subject = new SendMemberWelcome(
            $this->mailSender->mock,
            $this->memberPasswordResetLink->mock,
        );
    }

    public function test_it_sends_welcome_email_with_reset_and_login_urls(): void
    {
        $memberId = MemberId::create(1);
        $setPasswordUrl = 'https://example.test/reset-password/abc?email=jan%40example.com';
        $loginUrl = 'https://example.test/admin/login';

        $this->memberPasswordResetLink->expectsGenerate($memberId, $setPasswordUrl);

        Filament::shouldReceive('getLoginUrl')->once()->andReturn($loginUrl);

        $this->mailSender->expectsSend(new MemberWelcome(
            recipient: new Recipient('Vries, Jan de', 'jan@example.com'),
            setPasswordUrl: $setPasswordUrl,
            loginUrl: $loginUrl,
        ));

        $this->subject->handle(new MemberAccountProvisioned(
            memberId: $memberId,
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
        ));
    }
}
