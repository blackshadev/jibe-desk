<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class BillableItemDbInstanceRepository implements BillableItemInstanceRepository
{
    public function removeMany(MemberId $memberId, BillableItemIdList $billableItemIds): void
    {
        BillableItemInstance::query()
            ->where('member_id', $memberId->value)
            ->whereIn('billable_item_id', $billableItemIds->toIntArray())
            ->whereNull('end_date')
            ->update(
                [
                    'end_date' => CarbonImmutable::now(),
                ]
            );
    }

    public function add(MemberId $memberId, BillableItemId $billableItemId, ?DateTimeInterface $endDate = null): BillableItemInstanceId
    {
        $billableItem = BillableItem::findOrFail($billableItemId->value);
        $instance = BillableItemInstance::create([
            'member_id' => $memberId->value,
            'billable_item_id' => $billableItemId->value,
            'start_date' => CarbonImmutable::now(),
            'end_date' => $endDate,
            'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
        ]);

        return BillableItemInstanceId::create($instance->id);
    }

    public function ensure(MemberId $memberId, BillableItemId $billableItemId): void
    {
        $billableItem = BillableItem::findOrFail($billableItemId->value);

        BillableItemInstance::firstOrCreate([
            'member_id' => $memberId->value,
            'billable_item_id' => $billableItemId->value,
            'end_date' => null,
            'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
        ], [
            'start_date' => CarbonImmutable::now(),
        ]);
    }

    public function stop(BillableItemInstanceId $instanceId): void
    {
        BillableItemInstance::query()
            ->where('id', $instanceId->value)
            ->update(
                [
                    'end_date' => CarbonImmutable::now(),
                ]
            );
    }
}
