<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\SepaBatchEmailData;
use App\Domain\Invoices\SepaBatchId;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class SepaBatchEmailDataTest extends UnitTestCase
{
    public function test_it_exposes_its_properties(): void
    {
        $id = SepaBatchId::create(42);
        $date = CarbonImmutable::parse('2026-06-15');
        $total = new CompoundPrice(345.50, 72.56);

        $subject = new SepaBatchEmailData(
            id: $id,
            invoiceDate: $date,
            invoiceCount: 12,
            total: $total,
        );

        static::assertSame($id, $subject->id);
        static::assertSame($date, $subject->invoiceDate);
        static::assertSame(12, $subject->invoiceCount);
        static::assertSame($total, $subject->total);
    }
}
