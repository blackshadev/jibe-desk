<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyStoppedMembership;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class ApplyStoppedMembershipExpectation
{
    private function __construct(
        public MockInterface&ApplyStoppedMembership $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ApplyStoppedMembership::class));
    }

    public function expectsApply(MemberId $memberId): void
    {
        $this->mock
            ->expects('apply')
            ->with(equalTo($memberId))
            ->andReturnNull();
    }
}
