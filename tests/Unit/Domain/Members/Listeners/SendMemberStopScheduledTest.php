<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\MemberStopScheduled;
use App\Domain\Members\Listeners\SendMemberStopScheduled;
use App\Domain\Members\Mails\MemberStopped;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendMemberStopScheduledTest extends UnitTestCase
{
    private MailSenderExpectation $mailSender;

    private SendMemberStopScheduled $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mailSender = MailSenderExpectation::create();

        $this->subject = new SendMemberStopScheduled($this->mailSender->mock);
    }

    public function test_it_emails_the_member_about_the_scheduled_stop(): void
    {
        $event = new MemberStopScheduled(
            memberId: MemberId::create(1),
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            stoppedAt: CarbonImmutable::parse('2026-12-31 23:59:59'),
            isImmediate: false,
        );

        $this->mailSender->expectsSend(new MemberStopped(
            recipient: new Recipient('Vries, Jan de', 'jan@example.com'),
            stopDate: '31-12-2026',
            isImmediate: false,
        ));

        $this->subject->handle($event);
    }
}
