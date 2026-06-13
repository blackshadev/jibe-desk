<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\Billing\BillableItemsViewRepository;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use App\Models\BillableItemInstance;
use App\Models\InvoiceLine;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;

final class BillableItemsViewDbRepository implements BillableItemsViewRepository
{
    #[Override]
    public function listBillableMembers(DateTimeInterface $when): MemberIdList
    {
        /** @var int[] $all */
        $all = $this
            ->billableItemQuery($when)
            ->select('billable_item_instances.member_id')
            ->distinct()
            ->pluck('member_id')
            ->all();

        return MemberIdList::fromArray($all);
    }

    #[Override]
    public function listBillableItemsForMember(DateTimeInterface $when, MemberId $memberId): BillableItemList
    {
        $billingItems = $this
            ->billableItemQuery($when)
            ->with('billableItem')
            ->where('member_id', $memberId->value)
            ->get()
            ->map(static fn (BillableItemInstance $instance) => $instance->billableItem->toInvoiceBillableItem())
            ->all();

        return new BillableItemList($billingItems);
    }

    /** @return Builder<BillableItemInstance> */
    private function billableItemQuery(DateTimeInterface $when): Builder
    {
        $date = new Carbon($when)
            ->firstOfMonth()
            ->format('Y-m-d');

        return BillableItemInstance::query()
            ->select('billable_item_instances.*')
            ->joinRelationship('billableItem')
            ->where('billable_item_instances.start_date', '<=', $when)
            ->where(
                static fn (Builder $query) => $query
                    ->whereNull('billable_item_instances.end_date')
                    ->orWhere('billable_item_instances.end_date', '>', $when),
            )
            ->whereNotExists(
                InvoiceLine::query()
                    ->select('invoice_lines.*')
                    ->joinRelationship('invoice')
                    ->whereColumn('invoices.member_id', 'billable_item_instances.member_id')
                    ->whereColumn('invoice_lines.billable_item_id', 'billable_items.id')
                    ->when(
                        DB::connection()->getDriverName() === 'sqlite',
                        static function (Builder $query) use ($date): void {
                            $query->whereRaw(
                                "strftime('%Y-%m-01', invoices.date) > date(?, '-' || billable_item_instances.bill_cycle_in_months || ' months')",
                                [$date],
                            );
                        },
                        static function (Builder $query) use ($date): void {
                            $query->whereRaw(
                                "DATE_TRUNC('month', invoices.date) > '{$date}'::date - MAKE_INTERVAL(0, billable_item_instances.bill_cycle_in_months)",
                            );
                        },
                    ),
            );
    }
}
