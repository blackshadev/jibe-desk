<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\SepaExportInvoice;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class SepaExportInvoiceTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $invoiceId = InvoiceId::create(42);
        $mandateId = new MandateId(MemberId::create(1), PaymentInformationId::create(2));
        $mandateDate = CarbonImmutable::parse('2026-01-15');
        $total = new CompoundPrice(100.0, 21.0);

        $subject = new SepaExportInvoice(
            invoiceId: $invoiceId,
            invoiceNumber: 'INV-2026-001',
            recipientName: 'Jan de Vries',
            total: $total,
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            mandateId: $mandateId,
            paymentInformationId: PaymentInformationId::create(2),
            mandateDate: $mandateDate,
            lastCollectedAt: CarbonImmutable::parse('2026-02-15'),
        );

        static::assertSame($invoiceId, $subject->invoiceId);
        static::assertSame('INV-2026-001', $subject->invoiceNumber);
        static::assertSame('Jan de Vries', $subject->recipientName);
        static::assertSame($total, $subject->total);
        static::assertSame('NL91ABNA0417164300', $subject->iban);
        static::assertSame('ABNANL2A', $subject->bic);
        static::assertSame($mandateId, $subject->mandateId);
        static::assertSame(2, $subject->paymentInformationId->value);
        static::assertSame($mandateDate, $subject->mandateDate);
        static::assertSame('2026-02-15', $subject->lastCollectedAt?->format('Y-m-d'));
    }

    public function test_amount_in_cents(): void
    {
        $subject = new SepaExportInvoice(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'INV-001',
            recipientName: 'Test',
            total: new CompoundPrice(99.95, 20.99),
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            mandateId: new MandateId(MemberId::create(1), PaymentInformationId::create(1)),
            paymentInformationId: PaymentInformationId::create(1),
            mandateDate: CarbonImmutable::parse('2026-01-01'),
            lastCollectedAt: null,
        );

        static::assertSame(9995, $subject->amountInCents());
    }
}
