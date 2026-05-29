<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Activities\ActivityId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyActivityBilling;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;
use function PHPUnit\Framework\equalTo;

final readonly class ApplyActivityBillingExpectation
{
    private function __construct(public MockInterface&ApplyActivityBilling $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(ApplyActivityBilling::class));
    }

    public function expectsApply(MemberId $memberId, ActivityId $activityId): void
    {
        $this->mock
            ->expects('apply')
            ->with(equalTo($memberId), equalTo($activityId))
            ->andReturnNull();
    }

    public function allowsApply(): void
    {
        $this->mock
            ->allows('apply')
            ->andReturnNull();
    }

    public function expectsStop(BillableItemInstanceId $instanceId): void
    {
        $this->mock
            ->expects('stop')
            ->with(equalTo($instanceId));
    }

    public function expectsStopNever(): void
    {
        $this->mock
            ->expects('stop')
            ->never();
    }
}
