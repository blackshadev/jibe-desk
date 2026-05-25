<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

final readonly class BillableItemIdList
{
    /** @param BillableItemId[] $ids */
    public function __construct(private array $ids)
    {
    }

    /** @return int[] */
    public function toIntArray(): array
    {
        return array_map(fn (BillableItemId $id): int => $id->value, $this->ids);
    }
}
