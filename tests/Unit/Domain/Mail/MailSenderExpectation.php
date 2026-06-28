<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\MailSender;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class MailSenderExpectation
{
    private function __construct(
        public MockInterface&MailSender $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(MailSender::class));
    }

    public function expectsSend(BaseMail $mail): void
    {
        $this->mock
            ->expects('send')
            ->with(equalTo($mail));
    }
}
