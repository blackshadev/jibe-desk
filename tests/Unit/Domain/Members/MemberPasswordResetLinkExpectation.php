<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberPasswordResetLink;
use Mockery;
use Mockery\MockInterface;
use Throwable;

use function PHPUnit\Framework\equalTo;

final readonly class MemberPasswordResetLinkExpectation
{
    private function __construct(
        public MockInterface&MemberPasswordResetLink $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(MemberPasswordResetLink::class));
    }

    public function expectsGenerate(MemberId $memberId, string $setPasswordUrl): void
    {
        $this->mock
            ->expects('generate')
            ->with(equalTo($memberId))
            ->andReturn($setPasswordUrl);
    }

    public function expectsGenerateToThrow(MemberId $memberId, Throwable $exception): void
    {
        $this->mock
            ->expects('generate')
            ->with(equalTo($memberId))
            ->andThrow($exception);
    }
}
