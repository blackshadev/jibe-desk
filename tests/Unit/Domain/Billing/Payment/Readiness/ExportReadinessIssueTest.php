<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use Tests\UnitTestCase;

final class ExportReadinessIssueTest extends UnitTestCase
{
    public function test_create_for_invoice_copies_the_invoice_facts(): void
    {
        $candidate = new SepaCollectionCandidate(
            invoiceId: InvoiceId::create(42),
            invoiceNumber: 'I-2026-000042',
            recipientName: 'Jan de Vries',
            memberId: MemberId::create(7),
            total: new CompoundPrice(100.0, 21.0),
            status: InvoiceStatus::Pending,
            sequenceType: SequenceType::Recurring,
        );

        $issue = ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingIban, $candidate);

        static::assertSame(ExportReadinessReason::MissingIban, $issue->reason);
        static::assertSame($candidate->invoiceId, $issue->invoiceId);
        static::assertSame('I-2026-000042', $issue->invoiceNumber);
        static::assertSame($candidate->memberId, $issue->memberId);
        static::assertSame('Jan de Vries', $issue->memberName);
        static::assertSame($candidate->total, $issue->amount);
        static::assertNull($issue->detail);
    }

    public function test_create_for_batch_has_no_invoice(): void
    {
        $issue = ExportReadinessIssue::forBatch(
            ExportReadinessReason::NoticePeriodTooShort,
            '3 dagen tussen sluiting en incassodatum; minimaal 14.',
        );

        static::assertNull($issue->invoiceId);
        static::assertNull($issue->invoiceNumber);
        static::assertNull($issue->memberId);
        static::assertNull($issue->memberName);
        static::assertNull($issue->amount);
        static::assertSame('3 dagen tussen sluiting en incassodatum; minimaal 14.', $issue->detail);
    }
}
