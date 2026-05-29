<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Domain\Activities\ActivityId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use App\Models\Pivots\ActivityMember;
use App\Observers\ActivityMemberObserver;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplyActivityBillingExpectation;

final class ActivityMemberObserverTest extends FeatureTestCase
{
    private ApplyActivityBillingExpectation $applyActivity;

    private ActivityMemberObserver $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applyActivity = ApplyActivityBillingExpectation::create();

        $this->subject = new ActivityMemberObserver($this->applyActivity->mock);
    }

    public function test_it_applies_billing_when_pivot_is_created(): void
    {
        $memberId = MemberId::create(12);
        $activityId = ActivityId::create(23);

        $pivot = new ActivityMember([
            'activity_id' => $activityId->value,
            'member_id' => $memberId->value,
        ]);

        $this->applyActivity->expectsApply($memberId, $activityId);

        $this->subject->created($pivot);
    }

    public function test_it_stops_billing_when_pivot_is_deleted_and_has_instance_id(): void
    {
        $instanceId = 33;

        $pivot = new ActivityMember([
            'activity_id' => 1,
            'member_id' => 2,
            'billable_item_instance_id' => $instanceId,
        ]);

        $this->applyActivity->expectsStop(BillableItemInstanceId::create($instanceId));

        $this->subject->deleted($pivot);
    }

    public function test_it_does_not_stop_billing_when_pivot_deleted_without_instance(): void
    {
        $pivot = new ActivityMember([
            'activity_id' => 1,
            'member_id' => 2,
            'billable_item_instance_id' => null,
        ]);

        $this->applyActivity->expectsStopNever();

        $this->subject->deleted($pivot);
    }
}
