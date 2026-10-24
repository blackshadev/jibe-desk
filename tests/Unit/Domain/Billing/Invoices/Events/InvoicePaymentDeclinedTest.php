<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices\Events;

use App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined;
use App\Domain\Billing\Invoices\InvoiceId;
use Tests\UnitTestCase;

final class InvoicePaymentDeclinedTest extends UnitTestCase
{
    public function test_it_stores_invoice_id(): void
    {
        $event = new InvoicePaymentDeclined(InvoiceId::create(42));

        static::assertEquals(InvoiceId::create(42), $event->invoiceId);
    }
}
