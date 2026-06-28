<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceLineId;
use Tests\UnitTestCase;

final class AppliedInvoiceWithLineIdsTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $invoiceId = InvoiceId::create(42);
        $lineIds = [InvoiceLineId::create(1), InvoiceLineId::create(2)];

        $subject = new AppliedInvoiceWithLineIds(
            isNew: true,
            invoiceId: $invoiceId,
            lineIds: $lineIds,
        );

        static::assertTrue($subject->isNew);
        static::assertSame($invoiceId, $subject->invoiceId);
        static::assertSame($lineIds, $subject->lineIds);
    }

    public function test_it_stores_existing_invoice(): void
    {
        $subject = new AppliedInvoiceWithLineIds(
            isNew: false,
            invoiceId: InvoiceId::create(1),
            lineIds: [],
        );

        static::assertFalse($subject->isNew);
        static::assertSame([], $subject->lineIds);
    }
}
