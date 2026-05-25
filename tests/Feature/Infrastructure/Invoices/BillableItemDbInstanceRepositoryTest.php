<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Members\MemberId;
use App\Infrastructure\Invoices\Billing\BillableItemDbInstanceRepository;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Tests\FeatureTestCase;

final class BillableItemDbInstanceRepositoryTest extends FeatureTestCase
{
    private const NOW = '2023-01-01 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(self::NOW);
    }

    public function test_remove_instances_deletes_matching_records(): void
    {
        $member = Member::factory()->createQuietly();
        $memberId = MemberId::create($member->id);

        $billable = BillableItem::factory()->create();
        $otherBillable = BillableItem::factory()->create();
        $billableIds = new BillableItemIdList([BillableItemId::create($billable->id), BillableItemId::create($otherBillable->id)]);

        BillableItemInstance::factory()->create(['member_id' => $member->id, 'billable_item_id' => $billable->id, 'bill_cycle_in_months' => 12]);
        BillableItemInstance::factory()->create(['member_id' => $member->id, 'billable_item_id' => $otherBillable->id, 'bill_cycle_in_months' => 12]);

        $repo = new BillableItemDbInstanceRepository();

        $repo->removeMany($memberId, $billableIds);

        $this->assertDatabaseHas('billable_item_instances', ['member_id' => $member->id, 'billable_item_id' => $billable->id, 'end_date' => self::NOW]);
    }

    public function test_remove_instances_does_not_delete_other_members_records(): void
    {
        $member = Member::factory()->createQuietly();
        $other = Member::factory()->createQuietly();

        $billable = BillableItem::factory()->create();

        BillableItemInstance::factory()->create(['member_id' => $member->id, 'billable_item_id' => $billable->id, 'bill_cycle_in_months' => 12, 'start_date' => '2023-01-01']);
        BillableItemInstance::factory()->create(['member_id' => $other->id, 'billable_item_id' => $billable->id,  'bill_cycle_in_months' => 12]);

        $repo = new BillableItemDbInstanceRepository();

        $repo->removeMany(MemberId::create($member->id), new BillableItemIdList([BillableItemId::create($billable->id)]));

        $this->assertDatabaseHas('billable_item_instances', ['member_id' => $other->id, 'billable_item_id' => $billable->id]);
    }

    public function test_add_instance_creates_record_with_correct_bill_period(): void
    {
        $member = Member::factory()->createQuietly();
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        $repo = new BillableItemDbInstanceRepository();

        $repo->add(MemberId::create($member->id), BillableItemId::create($billable->id));

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'start_date' => self::NOW,
            'end_date' => null,
        ]);
    }

    public function test_ensure_creates_record_when_missing(): void
    {
        $member = Member::factory()->createQuietly();
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        $repo = new BillableItemDbInstanceRepository();

        $repo->ensure(MemberId::create($member->id), BillableItemId::create($billable->id));

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'end_date' => null,
        ]);
    }

    public function test_ensure_does_not_duplicate_active_record(): void
    {
        $member = Member::factory()->createQuietly();
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2023-01-01',
            'end_date' => null,
        ]);

        $repo = new BillableItemDbInstanceRepository();

        $repo->ensure(MemberId::create($member->id), BillableItemId::create($billable->id));

        self::assertSame(1, BillableItemInstance::query()->where('member_id', $member->id)->where('billable_item_id', $billable->id)->whereNull('end_date')->count());
    }
}
