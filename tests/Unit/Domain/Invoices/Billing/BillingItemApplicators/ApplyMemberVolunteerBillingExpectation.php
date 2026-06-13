<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class ApplyMemberVolunteerBillingExpectation
{
    private function __construct(
        public MockInterface&ApplyMemberVolunteerBilling $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ApplyMemberVolunteerBilling::class));
    }

    public function expectsApply(MemberId $memberId): void
    {
        $this->mock
            ->expects('apply')
            ->with(equalTo($memberId))
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
