<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\MandateId;
use App\Domain\Invoices\PaymentInformationId;
use App\Domain\Invoices\SepaExportInvoice;
use App\Domain\Members\MemberId;
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
            mandateDate: $mandateDate,
        );

        static::assertSame($invoiceId, $subject->invoiceId);
        static::assertSame('INV-2026-001', $subject->invoiceNumber);
        static::assertSame('Jan de Vries', $subject->recipientName);
        static::assertSame($total, $subject->total);
        static::assertSame('NL91ABNA0417164300', $subject->iban);
        static::assertSame('ABNANL2A', $subject->bic);
        static::assertSame($mandateId, $subject->mandateId);
        static::assertSame($mandateDate, $subject->mandateDate);
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
            mandateDate: CarbonImmutable::parse('2026-01-01'),
        );

        static::assertSame(9995, $subject->amountInCents());
    }
}
