<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBilling;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use Mockery;
use Mockery\MockInterface;
use function PHPUnit\Framework\equalTo;

/**
 * Expectation for ApplyMembershipBilling
 *
 * Keeps the mock tiny and provides a helper to allow the __invoke call.
 */
final readonly class ApplyMembershipBillingExpectation
{
    private function __construct(public MockInterface&ApplyMembershipBilling $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(ApplyMembershipBilling::class));
    }

    public function expectsApply(MemberId $memberId, MembershipId $membershipId): void
    {
        $this->mock
            ->expects('apply')
            ->with(equalTo($memberId), equalTo($membershipId))
            ->andReturnNull();
    }

    public function expectsApplyNever(): void
    {
        $this->mock
            ->expects('apply')
            ->never();
    }

    public function allowsApply(): void
    {
        $this->mock
            ->allows('apply')
            ->andReturnNull();
    }
}
