<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\Listeners;

use App\Domain\Mail\Listeners\LogOutgoingMail;
use App\Domain\Mail\OutgoingEmail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\TrackingId;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Override;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\Unit\Domain\Mail\OutgoingEmailRepositoryExpectation;
use Tests\Unit\Domain\Mail\TrackingIdGeneratorExpectation;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\UnitTestCase;

final class LogOutgoingMailTest extends UnitTestCase
{
    private OutgoingEmailRepositoryExpectation $outgoingEmailRepo;
    private MemberRepositoryExpectation $memberRepo;
    private ClockExpectation $clock;
    private TrackingIdGeneratorExpectation $trackingIdGenerator;
    private LogOutgoingMail $listener;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->outgoingEmailRepo = OutgoingEmailRepositoryExpectation::create();
        $this->memberRepo = MemberRepositoryExpectation::create();
        $this->clock = ClockExpectation::create();
        $this->trackingIdGenerator = TrackingIdGeneratorExpectation::create();

        $this->listener = new LogOutgoingMail(
            $this->outgoingEmailRepo->mock,
            $this->memberRepo->mock,
            $this->clock->mock,
            $this->trackingIdGenerator->mock,
        );
    }

    public function test_it_logs_an_outgoing_email(): void
    {
        $dt = new CarbonImmutable();
        $memberId = MemberId::create(42);
        $mailableClass = 'App\Domain\Invoices\Mails\InvoiceMail';
        $trackingId = TrackingId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $email = $this->createEmail(
            to: 'jan@example.com',
            toName: 'Jan de Vries',
            subject: 'Test subject',
            mailableClass: $mailableClass,
        );

        $this->clock->expectsNow($dt);

        $event = new MessageSending($email);

        $this->trackingIdGenerator
            ->mock
            ->shouldReceive('generate')
            ->once()
            ->andReturn($trackingId);
        $this->memberRepo->expectsGetByEmail('jan@example.com', $memberId);
        $this->outgoingEmailRepo->expectsQueue(new OutgoingEmail(
            $trackingId,
            $mailableClass,
            new Recipient('Jan de Vries', 'jan@example.com'),
            'Test subject',
            $memberId,
            $dt,
        ));

        $this->listener->handle($event);

        $trackingIdHeader = $email->getHeaders()->get('X-Tracking-ID');
        static::assertNotNull($trackingIdHeader);
        static::assertSame($trackingId->value, $trackingIdHeader->getBodyAsString());
    }

    public function test_it_skips_when_no_recipient(): void
    {
        $email = new Email();
        $email->subject('Test');
        $email->getHeaders()->addTextHeader('X-Mailable-Class', 'App\Domain\Invoices\Mails\InvoiceMail');

        $event = new MessageSending($email);

        $this->outgoingEmailRepo
            ->mock
            ->expects('queue')
            ->never();

        $this->listener->handle($event);
    }

    public function test_it_skips_when_no_mailable_class_header(): void
    {
        $email = $this->createEmail();

        $event = new MessageSending($email);

        $this->outgoingEmailRepo
            ->expectsNoQueue();

        $this->listener->handle($event);
    }

    /**
     * @param string $to
     * @param string|null $toName
     * @param string $subject
     * @param string|null $mailableClass
     */
    private function createEmail(
        ?string $to = 'example@example.com',
        ?string $toName = null,
        string $subject = 'Test',
        ?string $mailableClass = null,
    ): Email {
        $email = new Email();
        $email->subject($subject);

        if ($to !== null) {
            $email->to(new Address($to, $toName ?? ''));
        }

        if ($mailableClass !== null) {
            $email->getHeaders()->addTextHeader('X-Mailable-Class', $mailableClass);
        }

        return $email;
    }
}
