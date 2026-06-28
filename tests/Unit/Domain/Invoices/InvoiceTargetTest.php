<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceTarget;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class InvoiceTargetTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $memberId = MemberId::create(42);
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $batchId = InvoiceBatchId::create(9);

        $subject = new InvoiceTarget(
            memberId: $memberId,
            invoiceDate: $invoiceDate,
            batchId: $batchId,
        );

        static::assertSame($memberId, $subject->memberId);
        static::assertSame($invoiceDate, $subject->invoiceDate);
        static::assertSame($batchId, $subject->batchId);
    }

    public function test_it_allows_null_batch_id(): void
    {
        $subject = new InvoiceTarget(
            memberId: MemberId::create(1),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
        );

        static::assertNull($subject->batchId);
    }
}
