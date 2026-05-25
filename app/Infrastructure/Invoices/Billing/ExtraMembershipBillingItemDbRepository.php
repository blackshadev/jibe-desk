<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Models\ExtraMembershipItem;

final class ExtraMembershipBillingItemDbRepository implements ExtraMembershipBillingItemRepository
{
    public function getByCode(ExtraMembershipItemCode $code): BillableItemId
    {
        $model = ExtraMembershipItem::query()->where('code', $code)->firstOrFail();

        return BillableItemId::create($model->billable_item_id);
    }
}
