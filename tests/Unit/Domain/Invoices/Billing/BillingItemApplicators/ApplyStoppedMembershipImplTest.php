<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStoppedMembershipImpl;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\Unit\Domain\Invoices\Billing\BillableItemInstanceRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyStoppedMembershipImplTest extends UnitTestCase
{
    private BillableItemInstanceRepositoryExpectation $instanceRepo;
    private ClockExpectation $clock;
    private ApplyStoppedMembershipImpl $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->instanceRepo = BillableItemInstanceRepositoryExpectation::create();
        $this->clock = ClockExpectation::create();

        $this->subject = new ApplyStoppedMembershipImpl(
            $this->instanceRepo->mock,
            $this->clock->mock,
        );
    }

    public function test_apply_stops_all_instances_with_todays_date(): void
    {
        $memberId = MemberId::create(1);
        $now = CarbonImmutable::parse('2023-06-15');

        $this->clock->expectsNow($now);
        $this->instanceRepo->expectsStopAll($memberId, $now);

        $this->subject->apply($memberId);
    }
}
