<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\OutgoingEmail;
use App\Domain\Mail\OutgoingEmailRepository;
use App\Domain\Mail\TrackingId;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class OutgoingEmailRepositoryExpectation
{
    private function __construct(
        public MockInterface&OutgoingEmailRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(OutgoingEmailRepository::class));
    }

    public function expectsQueue(OutgoingEmail $expectedEmail): void
    {
        $this->mock
            ->expects('queue')
            ->with(equalTo($expectedEmail));
    }

    public function expectsMarkAsSent(TrackingId $trackingId, string $messageId, DateTimeInterface $sentAt): void
    {
        $this->mock
            ->expects('markAsSent')
            ->with(
                equalTo($trackingId),
                $messageId,
                $sentAt,
            );
    }

    public function expectsNoQueue(): void
    {
        $this->mock
            ->expects('queue')
            ->never();
    }

    public function expectsMarkAsSentNever(): void
    {
        $this->mock
            ->expects('markAsSent')
            ->never();
    }
}
