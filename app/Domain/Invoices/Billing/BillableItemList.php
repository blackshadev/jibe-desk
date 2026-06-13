<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use Webmozart\Assert\Assert;

final readonly class BillableItemList
{
    /** @param BillableItem[] $items */
    public function __construct(
        public array $items,
    ) {
        /** @mago-expect analysis:redundant-type-comparison */
        Assert::allIsInstanceOf($items, BillableItem::class);
    }
}
