<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class InvoicePaymentDeclinedMailDataTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $recipient = new Recipient('Jan de Vries', 'jan@example.com');
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $total = new CompoundPrice(100.0, 21.0);

        $subject = new InvoicePaymentDeclinedMailData(
            invoiceId: InvoiceId::create(42),
            invoiceNumber: 'INV-2026-001',
            recipient: $recipient,
            invoiceDate: $invoiceDate,
            total: $total,
            mandateNeedsReauthorisation: false,
        );

        static::assertSame(42, $subject->invoiceId->value);
        static::assertSame('INV-2026-001', $subject->invoiceNumber);
        static::assertSame($recipient, $subject->recipient);
        static::assertSame($invoiceDate, $subject->invoiceDate);
        static::assertSame($total, $subject->total);
        static::assertFalse($subject->mandateNeedsReauthorisation);
    }

    public function test_it_stores_the_reauthorisation_flag(): void
    {
        $subject = new InvoicePaymentDeclinedMailData(
            invoiceId: InvoiceId::create(42),
            invoiceNumber: 'INV-2026-001',
            recipient: new Recipient('Jan de Vries', 'jan@example.com'),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            total: new CompoundPrice(100.0, 21.0),
            mandateNeedsReauthorisation: true,
        );

        static::assertTrue($subject->mandateNeedsReauthorisation);
    }
}
