<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\SepaCategory;
use App\Domain\Billing\Payment\SepaExportInvoice;
use App\Domain\Billing\Payment\SepaExportInvoiceList;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class SepaExportInvoiceListTest extends UnitTestCase
{
    public function test_it_groups_the_invoices_by_category(): void
    {
        $credit = $this->invoice(1, new CompoundPrice(-25.0, -5.25), SequenceType::Recurring);
        $first = $this->invoice(2, new CompoundPrice(25.0, 5.25), SequenceType::First);
        $recurring = $this->invoice(3, new CompoundPrice(25.0, 5.25), SequenceType::Recurring);

        $list = new SepaExportInvoiceList([$credit, $first, $recurring]);

        static::assertSame([$credit], $list->forCategory(SepaCategory::Credit));
        static::assertSame([$first], $list->forCategory(SepaCategory::DebitFirst));
        static::assertSame([$recurring], $list->forCategory(SepaCategory::DebitRecurring));
    }

    public function test_it_knows_which_categories_are_present(): void
    {
        $list = new SepaExportInvoiceList([
            $this->invoice(1, new CompoundPrice(-25.0, -5.25), SequenceType::Recurring),
        ]);

        static::assertTrue($list->hasForCategory(SepaCategory::Credit));
        static::assertFalse($list->hasForCategory(SepaCategory::DebitFirst));
        static::assertFalse($list->hasForCategory(SepaCategory::DebitRecurring));
    }

    public function test_it_knows_whether_it_holds_credits_or_debits(): void
    {
        $credit = $this->invoice(1, new CompoundPrice(-25.0, -5.25), SequenceType::Recurring);
        $first = $this->invoice(2, new CompoundPrice(25.0, 5.25), SequenceType::First);
        $recurring = $this->invoice(3, new CompoundPrice(25.0, 5.25), SequenceType::Recurring);

        $creditOnly = new SepaExportInvoiceList([$credit]);
        static::assertTrue($creditOnly->hasCredit());
        static::assertFalse($creditOnly->hasDebit());

        $debitOnly = new SepaExportInvoiceList([$first, $recurring]);
        static::assertFalse($debitOnly->hasCredit());
        static::assertTrue($debitOnly->hasDebit());

        $mixed = new SepaExportInvoiceList([$credit, $first]);
        static::assertTrue($mixed->hasCredit());
        static::assertTrue($mixed->hasDebit());

        $empty = new SepaExportInvoiceList([]);
        static::assertFalse($empty->hasCredit());
        static::assertFalse($empty->hasDebit());
    }

    public function test_it_never_categorises_an_invoice_without_an_amount(): void
    {
        $zero = $this->invoice(1, new CompoundPrice(0.0, 0.0), SequenceType::Recurring);

        $list = new SepaExportInvoiceList([$zero]);

        foreach (SepaCategory::cases() as $category) {
            static::assertSame([], $list->forCategory($category));
            static::assertFalse($list->hasForCategory($category));
        }
    }

    public function test_an_empty_list_has_no_categories(): void
    {
        $list = new SepaExportInvoiceList([]);

        foreach (SepaCategory::cases() as $category) {
            static::assertSame([], $list->forCategory($category));
            static::assertFalse($list->hasForCategory($category));
        }
    }

    public function test_it_keeps_the_invoices_in_order(): void
    {
        $first = $this->invoice(1, new CompoundPrice(25.0, 5.25), SequenceType::First);
        $second = $this->invoice(2, new CompoundPrice(50.0, 10.5), SequenceType::First);

        $list = new SepaExportInvoiceList([$first, $second]);

        static::assertSame([$first, $second], $list->forCategory(SepaCategory::DebitFirst));
    }

    private function invoice(int $invoiceId, CompoundPrice $total, SequenceType $sequenceType): SepaExportInvoice
    {
        return new SepaExportInvoice(
            invoiceId: InvoiceId::create($invoiceId),
            invoiceNumber: sprintf('INV-%03d', $invoiceId),
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
