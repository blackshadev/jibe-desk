<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Subscriptions;

use App\Domain\Billing\BillableItems\BillableItemId;
use App\Domain\Billing\BillableItems\BillableItemIdList;
use App\Domain\Billing\Subscriptions\BillableItemInstanceId;
use App\Domain\Billing\Subscriptions\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;

final class BillableItemDbInstanceRepository implements BillableItemInstanceRepository
{
    #[Override]
    public function removeMany(MemberId $memberId, BillableItemIdList $billableItemIds): void
    {
        BillableItemInstance::query()
            ->where('member_id', $memberId->value)
            ->whereIn('billable_item_id', $billableItemIds->toIntArray())
            ->whereNull('end_date')
            ->update(
                [
                    'end_date' => CarbonImmutable::now(),
                ],
            );
    }

    #[Override]
    public function add(MemberId $memberId, BillableItemId $billableItemId, ?DateTimeInterface $endDate = null, ?DateTimeInterface $startDate = null): BillableItemInstanceId
    {
        $billableItem = BillableItem::findOrFail($billableItemId->value);
        $instance = BillableItemInstance::create([
            'member_id' => $memberId->value,
            'billable_item_id' => $billableItemId->value,
            'start_date' => $startDate ?? CarbonImmutable::now(),
            'end_date' => $endDate,
            'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
            'bill_month' => $billableItem->bill_month,
        ]);

        return BillableItemInstanceId::create($instance->id);
    }

    #[Override]
    public function ensure(MemberId $memberId, BillableItemId $billableItemId): void
    {
        $billableItem = BillableItem::findOrFail($billableItemId->value);

        BillableItemInstance::firstOrCreate([
            'member_id' => $memberId->value,
            'billable_item_id' => $billableItemId->value,
            'end_date' => null,
            'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
            'bill_month' => $billableItem->bill_month,
        ], [
            'start_date' => CarbonImmutable::now(),
        ]);
    }

    #[Override]
    public function stop(BillableItemInstanceId $instanceId, DateTimeInterface $endDate): void
    {
        $instance = BillableItemInstance::query()
            ->with('billableItem')
            ->find($instanceId->value);

        $billPeriod = $instance->billableItem->bill_period->toPeriodName();

        $actualEndDate = CarbonImmutable::instance($endDate)->endOf($billPeriod);

        $instance->update([
            'end_date' => $actualEndDate,
        ]);
    }

    #[Override]
    public function stopAll(MemberId $memberId, DateTimeInterface $endDate): void
    {
        $instances = BillableItemInstance::query()
            ->with('billableItem')
            ->where('member_id', $memberId->value)
            ->whereNull('end_date')
            ->get();

        /** @var BillableItemInstance $instance */
        foreach ($instances as $instance) {
            $billPeriod = $instance->billableItem->bill_period->toPeriodName();

            $actualEndDate = CarbonImmutable::instance($endDate)->endOf($billPeriod);

            $instance->update([
                'end_date' => $actualEndDate,
            ]);
        }
    }

    #[Override]
    public function reopenAll(MemberId $memberId, DateTimeInterface $endDate): void
    {
        $instances = BillableItemInstance::query()
            ->with('billableItem')
            ->where('member_id', $memberId->value)
            ->whereNotNull('end_date')
            ->get();

        $instances
            ->filter(static function (BillableItemInstance $instance) use ($endDate): bool {
                $billPeriod = $instance->billableItem->bill_period->toPeriodName();
                $scheduledEndDate = CarbonImmutable::instance($endDate)->endOf($billPeriod);

                return $instance->end_date?->isSameDay($scheduledEndDate) ?? false;
            })
            ->each(static fn (BillableItemInstance $instance) => $instance->update(['end_date' => null]));
    }

    #[Override]
    public function updateEndDate(BillableItemInstanceId $instanceId, ?DateTimeInterface $endDate): void
    {
        BillableItemInstance::query()
            ->where('id', $instanceId->value)
            ->update(['end_date' => $endDate]);
    }
}
