<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\Billing\BillableItem as BillableItemEntity;
use App\Domain\Members\MemberId;
use App\Infrastructure\Invoices\Billing\BillableItemsViewDbRepository;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\Membership;
use DateTimeImmutable;
use Tests\FeatureTestCase;

final class BillableItemsViewDbRepositoryTest extends FeatureTestCase
{
    public function test_list_billable_members_returns_distinct_active_members(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $memberOne = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $memberTwo = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $billableOne = BillableItem::factory()->create(['bill_period' => 'monthly']);
        $billableTwo = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $memberOne->id,
            'billable_item_id' => $billableOne->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);
        BillableItemInstance::factory()->create([
            'member_id' => $memberOne->id,
            'billable_item_id' => $billableTwo->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);
        BillableItemInstance::factory()->create([
            'member_id' => $memberTwo->id,
            'billable_item_id' => $billableOne->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $repo = new BillableItemsViewDbRepository();

        $members = $repo->listBillableMembers($when)->ids;

        static::assertCount(2, $members);
        static::assertEqualsCanonicalizing([$memberOne->id, $memberTwo->id], array_map(static fn (MemberId $memberId): int => $memberId->value, $members));
    }

    public function test_list_billable_members_excludes_future_and_ended_instances(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $futureMember = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $endedMember = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $futureMember->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-06-01',
            'end_date' => null,
        ]);
        BillableItemInstance::factory()->create([
            'member_id' => $endedMember->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-01',
        ]);

        $repo = new BillableItemsViewDbRepository();

        $members = $repo->listBillableMembers($when)->ids;

        static::assertCount(0, $members);
    }

    public function test_list_billable_members_excludes_deleted_members(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $futureMember = Member::factory()->createQuietly(['deleted_at' => '2026-05-15T00:00:00Z', 'membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $futureMember->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-04-01',
            'end_date' => null,
        ]);

        $repo = new BillableItemsViewDbRepository();

        $members = $repo->listBillableMembers($when)->ids;

        static::assertCount(0, $members);
    }

    public function test_list_billable_members_includes_future_deleted_members(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $futureMember = Member::factory()->createQuietly(['deleted_at' => '2026-07-15T00:00:00Z', 'membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $futureMember->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-04-01',
            'end_date' => null,
        ]);

        $repo = new BillableItemsViewDbRepository();

        $members = $repo->listBillableMembers($when)->ids;

        static::assertCount(1, $members);
    }

    public function test_list_billable_items_for_member_returns_domain_items(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create([
            'description' => 'Member fee',
            'price' => 10.0,
            'vat' => 2.1,
            'bill_period' => 'monthly',
        ]);

        BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $repo = new BillableItemsViewDbRepository();

        $items = $repo->listBillableItemsForMember($when, MemberId::create($member->id))->items;

        static::assertCount(1, $items);
        static::assertInstanceOf(BillableItemEntity::class, $items[0]);
        static::assertSame($billable->id, $items[0]->id->value);
        static::assertSame(10.0, $items[0]->price->price);
        static::assertSame(2.1, $items[0]->price->vat);
        static::assertSame(1.0, $items[0]->quantity);
        static::assertSame('Member fee', $items[0]->description);
    }

    public function test_list_billable_items_for_member_excludes_already_invoiced_items_within_cycle(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->create([
                'date' => '2026-05-05',
            ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'billable_item_id' => $billable->id,
            'description' => 'Member fee',
            'price' => 10.0,
            'vat' => 2.1,
            'quantity' => 1.0,
        ]);

        $repo = new BillableItemsViewDbRepository();

        $items = $repo->listBillableItemsForMember($when, MemberId::create($member->id))->items;

        static::assertCount(0, $items);
    }

    public function test_list_billable_items_for_member_includes_items_outside_cycle_window(): void
    {
        $when = new DateTimeImmutable('2026-05-15');
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly(['membership_id' => $membership->id]);
        $billable = BillableItem::factory()->create(['bill_period' => 'monthly']);

        BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'bill_cycle_in_months' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->create([
                'date' => '2026-01-05',
            ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'billable_item_id' => $billable->id,
            'description' => 'Member fee',
            'price' => 10.0,
            'vat' => 2.1,
            'quantity' => 1.0,
        ]);

        $repo = new BillableItemsViewDbRepository();

        $items = $repo->listBillableItemsForMember($when, MemberId::create($member->id))->items;

        static::assertCount(1, $items);
    }
}
