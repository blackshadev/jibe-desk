<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use InvalidArgumentException;
use stdClass;
use Tests\UnitTestCase;

final class BankTransactionIdListTest extends UnitTestCase
{
    public function test_it_creates_from_array(): void
    {
        $subject = BankTransactionIdList::fromArray([1, 2, 3]);

        static::assertSame([1, 2, 3], array_map(static fn (BankTransactionId $id): int => $id->value, $subject->ids));
    }

    public function test_it_accepts_empty_array(): void
    {
        $subject = new BankTransactionIdList([]);

        static::assertSame([], $subject->ids);
    }

    public function test_it_rejects_invalid_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BankTransactionIdList([new stdClass()]);
    }

    public function test_as_ints(): void
    {
        $input = [1, 2, 3];

        $subject = BankTransactionIdList::fromArray($input);

        static::assertSame($input, $subject->asInts());
    }
}
