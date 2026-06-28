<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activities;

use App\Domain\Activities\Activity;
use App\Domain\Activities\ActivityId;
use App\Domain\Invoices\Billing\BillableItemId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class ActivityTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $id = ActivityId::create(42);
        $billableItemId = BillableItemId::create(10);
        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-12-31');

        $subject = new Activity(
            id: $id,
            billableItemId: $billableItemId,
            startDate: $startDate,
            endDate: $endDate,
        );

        static::assertSame($id, $subject->id);
        static::assertSame($billableItemId, $subject->billableItemId);
        static::assertSame($startDate, $subject->startDate);
        static::assertSame($endDate, $subject->endDate);
    }

    public function test_it_allows_null_end_date(): void
    {
        $subject = new Activity(
            id: ActivityId::create(1),
            billableItemId: BillableItemId::create(1),
            startDate: CarbonImmutable::parse('2026-01-01'),
            endDate: null,
        );

        static::assertNull($subject->endDate);
    }
}
