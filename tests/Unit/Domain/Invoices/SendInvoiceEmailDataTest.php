<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\SendInvoiceEmailData;
use Tests\UnitTestCase;

final class SendInvoiceEmailDataTest extends UnitTestCase
{
    public function test_it_exposes_its_properties(): void
    {
        $invoiceId = InvoiceId::create(42);

        $subject = new SendInvoiceEmailData(
            invoiceId: $invoiceId,
            isResend: true,
        );

        static::assertSame($invoiceId, $subject->invoiceId);
        static::assertTrue($subject->isResend);
    }

    public function test_it_defaults_to_not_being_a_resend(): void
    {
        $subject = new SendInvoiceEmailData(invoiceId: InvoiceId::create(42));

        static::assertFalse($subject->isResend);
    }
}
