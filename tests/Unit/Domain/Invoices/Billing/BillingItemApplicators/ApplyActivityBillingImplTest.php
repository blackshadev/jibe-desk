<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Activities\Activity as ActivityDomain;
use App\Domain\Activities\ActivityId;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyActivityBillingImpl;
use App\Domain\Members\MemberId;
use Tests\Unit\Domain\Activities\ActivityRepositoryExpectation;
use Tests\Unit\Domain\Invoices\BillableItemRepositoryExpectation;
use Tests\UnitTestCase;
use DateTimeImmutable;
use Override;

final class ApplyActivityBillingImplTest extends UnitTestCase
{
    private BillableItemRepositoryExpectation $billableItems;

    private ActivityRepositoryExpectation $activities;

    private ApplyActivityBillingImpl $subject;

    #[Override]
    protected function setup(): void
    {
        parent::setup();
        $this->activities = ActivityRepositoryExpectation::create();
        $this->billableItems = BillableItemRepositoryExpectation::create();

        $this->subject = new ApplyActivityBillingImpl($this->activities->mock, $this->billableItems->mock);
    }

    public function test_it_fetches_activity_and_creates_billable_item_instance(): void
    {
        $memberId = MemberId::create(1);
        $activityId = ActivityId::create(2);
        $billableItemId = BillableItemId::create(10);
        $instanceId = BillableItemInstanceId::create(11);

        $activity = new ActivityDomain($activityId, $billableItemId, new DateTimeImmutable('2023-01-01'), new DateTimeImmutable('2023-12-31'));

        $this->activities->expectsGetById($activityId, $activity);
        $this->billableItems->expectsAdd($memberId, $activity->billableItemId, $activity->endDate, $instanceId);
        $this->activities->expectsAttach($activityId, $memberId, $instanceId);

        $this->subject->apply($memberId, $activityId);
    }

    public function test_it_passes_activity_with_null_end_date(): void
    {
        $memberId = MemberId::create(1);
        $activityId = ActivityId::create(2);
        $billableItemId = BillableItemId::create(10);
        $instanceId = BillableItemInstanceId::create(11);

        $activity = new ActivityDomain($activityId, $billableItemId, new DateTimeImmutable('2023-01-01'), null);

        $this->activities->expectsGetById($activityId, $activity);
        $this->billableItems->expectsAdd($memberId, $activity->billableItemId, null, $instanceId);
        $this->activities->expectsAttach($activityId, $memberId, $instanceId);

        $this->subject->apply($memberId, $activityId);
    }

    public function test_stop_forwards_to_repository(): void
    {
        $instanceId = BillableItemInstanceId::create(11);

        $this->billableItems->expectsStop($instanceId);

        $this->subject->stop($instanceId);
    }
}
