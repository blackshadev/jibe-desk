<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use InvalidArgumentException;
use Tests\UnitTestCase;

final class MemberIdListTest extends UnitTestCase
{
    public function test_it_creates_from_array(): void
    {
        $subject = MemberIdList::fromArray([1, 2, 3]);

        self::assertSame([1, 2, 3], array_map(static fn (MemberId $id): int => $id->value, $subject->ids));
    }

    public function test_it_rejects_invalid_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        new MemberIdList([new \stdClass()]);
    }
}
