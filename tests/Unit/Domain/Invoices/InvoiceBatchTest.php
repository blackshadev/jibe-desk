<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceBatch;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class InvoiceBatchTest extends UnitTestCase
{
    public function test_it_stores_invoice_date(): void
    {
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $sepaTransferDate = CarbonImmutable::parse('2026-06-08');

        $subject = new InvoiceBatch(invoiceDate: $invoiceDate, sepaTransferDate: $sepaTransferDate);

        static::assertSame($invoiceDate, $subject->invoiceDate);
        static::assertSame($sepaTransferDate, $subject->sepaTransferDate);
    }
}
