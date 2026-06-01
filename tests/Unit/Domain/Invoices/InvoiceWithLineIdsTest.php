<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceLineId;
use App\Domain\Invoices\InvoiceWithLineIds;
use Tests\UnitTestCase;

final class InvoiceWithLineIdsTest extends UnitTestCase
{
    public function testInvoicesWithLineIds(): void
    {
        $id = InvoiceId::create(42);
        $lines = [InvoiceLineId::create(11)];

        $subject = new InvoiceWithLineIds($id, $lines);

        self::assertSame($id, $subject->invoiceId);
        self::assertSame($lines, $subject->lineIds);
    }
}
