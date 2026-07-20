<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use InvalidArgumentException;
use stdClass;
use Tests\UnitTestCase;

final class InvoiceIdListTest extends UnitTestCase
{
    public function test_it_creates_from_array(): void
    {
        $subject = InvoiceIdList::fromArray([1, 2, 3]);

        static::assertSame([1, 2, 3], array_map(static fn (InvoiceId $id): int => $id->value, $subject->ids));
    }

    public function test_it_accepts_empty_array(): void
    {
        $subject = new InvoiceIdList([]);

        static::assertSame([], $subject->ids);
    }

    public function test_it_rejects_invalid_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InvoiceIdList([new stdClass()]);
    }
}
