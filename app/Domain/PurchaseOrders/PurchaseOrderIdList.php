<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use Webmozart\Assert\Assert;

final class PurchaseOrderIdList
{
    /** @param PurchaseOrderId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, PurchaseOrderId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(
            array_map(
                PurchaseOrderId::create(...),
                $array,
            ),
        );
    }
}
