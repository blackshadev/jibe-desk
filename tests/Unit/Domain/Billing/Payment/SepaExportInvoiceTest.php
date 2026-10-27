<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\SepaCategory;
use App\Domain\Billing\Payment\SepaExportInvoice;
use App\Domain\Billing\Payment\SequenceType;
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
            sequenceType: SequenceType::First,
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
        static::assertSame(SequenceType::First, $subject->sequenceType);
    }

    public function test_it_keeps_the_sequence_type(): void
    {
        $subject = new SepaExportInvoice(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'INV-001',
            recipientName: 'Test',
            total: new CompoundPrice(10.0, 2.1),
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            mandateId: new MandateId(MemberId::create(1), PaymentInformationId::create(1)),
            paymentInformationId: PaymentInformationId::create(1),
            mandateDate: CarbonImmutable::parse('2026-01-01'),
            lastCollectedAt: CarbonImmutable::parse('2026-02-01'),
            sequenceType: SequenceType::First,
        );

        static::assertSame(SequenceType::First, $subject->sequenceType);
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
            sequenceType: SequenceType::Recurring,
        );

        static::assertSame(9995, $subject->amountInCents());
    }

    public function test_category_follows_the_sign_and_the_sequence_type(): void
    {
        static::assertSame(
            SepaCategory::Credit,
            $this->invoice(new CompoundPrice(-10.0, -2.1), SequenceType::Recurring)->category(),
        );
        static::assertSame(
            SepaCategory::DebitFirst,
            $this->invoice(new CompoundPrice(10.0, 2.1), SequenceType::First)->category(),
        );
        static::assertSame(
            SepaCategory::DebitRecurring,
            $this->invoice(new CompoundPrice(10.0, 2.1), SequenceType::Recurring)->category(),
        );
    }

    public function test_is_credit_only_for_a_negative_total(): void
    {
        static::assertTrue($this->invoice(new CompoundPrice(-10.0, -2.1), SequenceType::Recurring)->isCredit());
        static::assertFalse($this->invoice(new CompoundPrice(10.0, 2.1), SequenceType::Recurring)->isCredit());
        static::assertFalse($this->invoice(new CompoundPrice(0.0, 0.0), SequenceType::Recurring)->isCredit());
    }

    private function invoice(CompoundPrice $total, SequenceType $sequenceType): SepaExportInvoice
    {
        return new SepaExportInvoice(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'INV-001',
            recipientName: 'Test',
            total: $total,
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            mandateId: new MandateId(MemberId::create(1), PaymentInformationId::create(1)),
            paymentInformationId: PaymentInformationId::create(1),
            mandateDate: CarbonImmutable::parse('2026-01-01'),
            lastCollectedAt: null,
            sequenceType: $sequenceType,
        );
    }
}
