<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberUserRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class MemberUserRepositoryExpectation
{
    private function __construct(
        public MockInterface&MemberUserRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(MemberUserRepository::class));
    }

    public function expectsProvision(MemberId $memberId): void
    {
        $this->mock
            ->expects('provision')
            ->with(equalTo($memberId));
    }
}
