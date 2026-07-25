<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use Webmozart\Assert\Assert;

final class BankTransactionIdList
{
    /** @param BankTransactionId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, BankTransactionId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(
            array_map(
                BankTransactionId::create(...),
                $array,
            ),
        );
    }

    /** @return int[] */
    public function asInts(): array
    {
        return array_map(static fn (BankTransactionId $bankTransactionId) => $bankTransactionId->value, $this->ids);
    }
}
