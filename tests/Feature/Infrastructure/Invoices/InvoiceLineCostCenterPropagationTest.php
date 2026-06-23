<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Members\MemberId;
use App\Infrastructure\Invoices\Billing\BillableItemsViewDbRepository;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\CostCenter;
use App\Models\Member;
use App\Models\Membership;
use DateTimeImmutable;
use Tests\FeatureTestCase;

final class InvoiceLineCostCenterPropagationTest extends FeatureTestCase
{
    public function test_list_billable_items_for_member_propagates_cost_center_from_billable_item(): void
    {
        $costCenter = CostCenter::factory()->create(['number' => '1000']);
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create([
            'bill_period' => 'monthly',
            'cost_center_id' => $costCenter->id,
        ]);

        BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $when = new DateTimeImmutable('2026-05-15');
        $repo = new BillableItemsViewDbRepository();
        $items = $repo->listBillableItemsForMember($when, MemberId::create($member->id))->items;

        static::assertCount(1, $items);
        static::assertSame($costCenter->id, $items[0]->costCenterId->value);
    }
}
