<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\Listeners\MarkMailAsSent;
use App\Domain\Mail\TrackingId;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Mockery;
use Override;
use Symfony\Component\Mime\Email;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\UnitTestCase;

final class MarkMailAsSentTest extends UnitTestCase
{
    private OutgoingEmailRepositoryExpectation $outgoingEmailRepo;
    private MarkMailAsSent $listener;
    private ClockExpectation $clockExpectation;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->outgoingEmailRepo = OutgoingEmailRepositoryExpectation::create();
        $this->clockExpectation = ClockExpectation::create();

        $this->listener = new MarkMailAsSent(
            $this->outgoingEmailRepo->mock,
            $this->clockExpectation->mock,
        );
    }

    public function test_it_marks_mail_as_sent_with_tracking_id(): void
    {
        $now = CarbonImmutable::now();
        $email = $this->createEmailWithTrackingId('550e8400-e29b-41d4-a716-446655440000');

        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->expects('getMessageId')->andReturn('smtp-message-123');
        $sentMessage->expects('getOriginalMessage')->andReturn($email);

        $event = new MessageSent($sentMessage);

        $this->clockExpectation->expectsNow($now);

        $this->outgoingEmailRepo->expectsMarkAsSent(
            TrackingId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            'smtp-message-123',
            $now,
        );

        $this->listener->handle($event);
    }

    public function test_it_skips_when_no_tracking_id(): void
    {
        $email = new Email();

        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->expects('getOriginalMessage')->andReturn($email);

        $event = new MessageSent($sentMessage);

        $this->outgoingEmailRepo
            ->expectsMarkAsSentNever();

        $this->listener->handle($event);
    }

    private function createEmailWithTrackingId(string $trackingId): Email
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Tracking-ID', $trackingId);

        return $email;
    }
}
