<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\SharedKernel\CompoundPrice;
use Tests\UnitTestCase;

final class SepaCollectionCandidateTest extends UnitTestCase
{
    public function test_amount_in_cents_rounds_the_price(): void
    {
        static::assertSame(9995, $this->candidate(new CompoundPrice(99.95, 20.99))->amountInCents());
        static::assertSame(-1050, $this->candidate(new CompoundPrice(-10.50, -2.21))->amountInCents());
    }

    public function test_is_debit_and_is_refund_split_on_the_sign(): void
    {
        $debit = $this->candidate(new CompoundPrice(10.0, 2.1));
        $refund = $this->candidate(new CompoundPrice(-10.0, -2.1));
        $zero = $this->candidate(new CompoundPrice(0.0, 0.0));

        static::assertTrue($debit->isDebit());
        static::assertFalse($debit->isRefund());

        static::assertFalse($refund->isDebit());
        static::assertTrue($refund->isRefund());

        static::assertFalse($zero->isDebit());
        static::assertFalse($zero->isRefund());
    }

    private function candidate(CompoundPrice $total): SepaCollectionCandidate
    {
        return new SepaCollectionCandidate(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'I-2026-000001',
            recipientName: 'Test',
            memberId: null,
            total: $total,
            status: InvoiceStatus::Pending,
            sequenceType: SequenceType::Recurring,
        );
    }
}
