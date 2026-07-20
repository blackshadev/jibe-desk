<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use Webmozart\Assert\Assert;

final class InvoiceIdList
{
    /** @param InvoiceId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, InvoiceId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(
            array_map(
                InvoiceId::create(...),
                $array,
            ),
        );
    }
}
