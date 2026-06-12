<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipList;
use App\Domain\Members\MembershipRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class MembershipRepositoryExpectation
{
    private function __construct(public MockInterface&MembershipRepository $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(MembershipRepository::class));
    }

    public function expectsGetById(MembershipId $membershipId, Membership $membership): void
    {
        $this->mock
            ->expects('getById')
            ->with(equalTo($membershipId))
            ->andReturn($membership);
    }

    public function expectsAll(MembershipList $result): void
    {
        $this->mock
            ->expects('all')
            ->andReturn($result);
    }

    public function expectsGetDefault(MembershipId $result): void
    {
        $this->mock
            ->expects('getDefault')
            ->andReturn($result);
    }
}
