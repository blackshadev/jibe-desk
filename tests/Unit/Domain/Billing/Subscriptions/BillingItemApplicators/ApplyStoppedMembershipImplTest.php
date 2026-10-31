<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyStoppedMembershipImpl;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Billing\Subscriptions\BillableItemInstanceRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyStoppedMembershipImplTest extends UnitTestCase
{
    private BillableItemInstanceRepositoryExpectation $instanceRepo;
    private ApplyStoppedMembershipImpl $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->instanceRepo = BillableItemInstanceRepositoryExpectation::create();

        $this->subject = new ApplyStoppedMembershipImpl(
            $this->instanceRepo->mock,
        );
    }

    public function test_apply_stops_all_instances_at_the_given_stop_date(): void
    {
        $memberId = MemberId::create(1);
        $stopDate = CarbonImmutable::parse('2026-12-31');

        $this->instanceRepo->expectsStopAll($memberId, $stopDate);

        $this->subject->apply($memberId, $stopDate);
    }

    public function test_clear_reopens_all_instances_for_the_given_stop_date(): void
    {
        $memberId = MemberId::create(1);
        $stopDate = CarbonImmutable::parse('2026-12-31');

        $this->instanceRepo->expectsReopenAll($memberId, $stopDate);

        $this->subject->clear($memberId, $stopDate);
    }
}
