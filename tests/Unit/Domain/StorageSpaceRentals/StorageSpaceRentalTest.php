<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\MemberId;
use App\Domain\StorageSpaceRentals\StorageSpaceRental;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class StorageSpaceRentalTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $id = StorageSpaceRentalId::create(42);
        $memberId = MemberId::create(1);
        $billableItemId = BillableItemId::create(10);
        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-12-31');

        $subject = new StorageSpaceRental(
            id: $id,
            memberId: $memberId,
            billableItemId: $billableItemId,
            startDate: $startDate,
            endDate: $endDate,
        );

        static::assertSame($id, $subject->id);
        static::assertSame($memberId, $subject->memberId);
        static::assertSame($billableItemId, $subject->billableItemId);
        static::assertSame($startDate, $subject->startDate);
        static::assertSame($endDate, $subject->endDate);
    }

    public function test_it_allows_null_end_date(): void
    {
        $subject = new StorageSpaceRental(
            id: StorageSpaceRentalId::create(1),
            memberId: MemberId::create(1),
            billableItemId: BillableItemId::create(1),
            startDate: CarbonImmutable::parse('2026-01-01'),
            endDate: null,
        );

        static::assertNull($subject->endDate);
    }
}
