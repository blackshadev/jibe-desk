<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableReason;
use RuntimeException;
use Tests\UnitTestCase;

final class InvoicePaymentDeclinedMailUnavailableExceptionTest extends UnitTestCase
{
    public function test_it_exposes_the_invoice_and_reason(): void
    {
        $invoiceId = InvoiceId::create(42);

        $exception = InvoicePaymentDeclinedMailUnavailableException::invoiceNotDeclined($invoiceId);

        static::assertSame($invoiceId, $exception->invoiceId);
        static::assertSame(InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotDeclined, $exception->reason);
    }

    public function test_each_factory_sets_its_own_reason(): void
    {
        $invoiceId = InvoiceId::create(42);

        static::assertSame(
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotFound,
            InvoicePaymentDeclinedMailUnavailableException::invoiceNotFound($invoiceId)->reason,
        );
        static::assertSame(
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotDeclined,
            InvoicePaymentDeclinedMailUnavailableException::invoiceNotDeclined($invoiceId)->reason,
        );
        static::assertSame(
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceIsCreditInvoice,
            InvoicePaymentDeclinedMailUnavailableException::invoiceIsCreditInvoice($invoiceId)->reason,
        );
        static::assertSame(
            InvoicePaymentDeclinedMailUnavailableReason::RecipientEmailMissing,
            InvoicePaymentDeclinedMailUnavailableException::recipientEmailMissing($invoiceId)->reason,
        );
    }

    public function test_it_describes_the_invoice_and_reason_in_the_message(): void
    {
        $exception = InvoicePaymentDeclinedMailUnavailableException::recipientEmailMissing(InvoiceId::create(42));

        static::assertSame(
            'Cannot create payment declined mail data for invoice 42 (recipient_email_missing).',
            $exception->getMessage(),
        );
    }

    public function test_it_is_a_runtime_exception(): void
    {
        $exception = InvoicePaymentDeclinedMailUnavailableException::invoiceNotFound(InvoiceId::create(42));

        static::assertInstanceOf(RuntimeException::class, $exception);
    }
}
