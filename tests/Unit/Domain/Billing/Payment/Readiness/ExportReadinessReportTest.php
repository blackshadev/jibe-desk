<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportOverride;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReport;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\SharedKernel\CompoundPrice;
use Tests\UnitTestCase;

final class ExportReadinessReportTest extends UnitTestCase
{
    public function test_it_is_blocked_by_broken_data_issues(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::MalformedIban)]);

        static::assertTrue($report->isBlocked());
        static::assertCount(1, $report->brokenDataIssues());
    }

    public function test_it_is_blocked_by_a_missing_mandate(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::MissingMandate)]);

        static::assertTrue($report->isBlocked());
        static::assertCount(1, $report->brokenDataIssues());
        static::assertCount(1, $report->blockingIssues());
    }

    public function test_it_is_blocked_by_an_invoice_without_an_iban(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::MissingIban)]);

        static::assertCount(1, $report->brokenDataIssues());
        static::assertTrue($report->isBlocked());
        static::assertFalse($report->isSatisfiedBy(new ExportConfirmation()));
    }

    public function test_it_requires_an_override_for_an_expired_mandate(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::ExpiredMandate)]);

        static::assertTrue($report->isBlocked());
        static::assertCount(1, $report->overridableIssues());
        static::assertFalse($report->isSatisfiedBy(new ExportConfirmation()));
    }

    public function test_it_accepts_an_overridden_expired_mandate_with_a_reason(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::ExpiredMandate)]);

        $confirmation = new ExportConfirmation(
            overrides: [
                new ExportOverride(
                    invoiceId: InvoiceId::create(1),
                    reason: ExportReadinessReason::ExpiredMandate,
                    note: 'Written confirmation received.',
                ),
            ],
        );

        static::assertTrue($report->isSatisfiedBy($confirmation));
    }

    public function test_it_requires_an_override_for_a_transfer_member_with_an_active_mandate(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::TransferWithActiveMandate)]);

        static::assertTrue($report->isBlocked());
        static::assertCount(1, $report->overridableIssues());
        static::assertFalse($report->isSatisfiedBy(new ExportConfirmation()));
        static::assertTrue($report->isSatisfiedBy(new ExportConfirmation(
            overrides: [
                new ExportOverride(
                    invoiceId: InvoiceId::create(1),
                    reason: ExportReadinessReason::TransferWithActiveMandate,
                    note: 'Member confirmed the collection.',
                ),
            ],
        )));
    }

    public function test_it_rejects_an_override_without_a_reason(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::ExpiredMandate)]);

        $confirmation = new ExportConfirmation(
            overrides: [
                new ExportOverride(
                    invoiceId: InvoiceId::create(1),
                    reason: ExportReadinessReason::ExpiredMandate,
                    note: '   ',
                ),
            ],
        );

        static::assertFalse($report->isSatisfiedBy($confirmation));
        static::assertCount(1, $report->unresolvedIssues($confirmation));
    }

    public function test_it_rejects_an_override_with_the_wrong_reason(): void
    {
        $report = $this->report([$this->invoiceIssue(ExportReadinessReason::ExpiredMandate)]);

        $confirmation = new ExportConfirmation(
            overrides: [
                new ExportOverride(
                    invoiceId: InvoiceId::create(1),
                    reason: ExportReadinessReason::MissingMandate,
                    note: 'Some reason.',
                ),
            ],
        );

        static::assertFalse($report->isSatisfiedBy($confirmation));
    }

    public function test_it_separates_batch_level_issues_from_broken_data(): void
    {
        $report = $this->report([
            ExportReadinessIssue::forBatch(ExportReadinessReason::NoticePeriodTooShort, '3 of 14 dagen.'),
            $this->invoiceIssue(ExportReadinessReason::MissingIban),
        ]);

        static::assertCount(1, $report->batchLevelIssues());
        static::assertCount(1, $report->brokenDataIssues());
        static::assertCount(2, $report->blockingIssues());
    }

    public function test_it_has_no_blockers_without_issues(): void
    {
        $report = $this->report([]);

        static::assertTrue($report->isEmpty());
        static::assertFalse($report->isBlocked());
        static::assertTrue($report->isSatisfiedBy(new ExportConfirmation()));
    }

    /** @param list<ExportReadinessIssue> $issues */
    private function report(array $issues): ExportReadinessReport
    {
        return new ExportReadinessReport(
            batchId: SepaBatchId::create(1),
            issues: $issues,
            noticePeriodDays: 14,
        );
    }

    private function invoiceIssue(ExportReadinessReason $reason): ExportReadinessIssue
    {
        $candidate = new SepaCollectionCandidate(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'I-2026-000001',
            recipientName: 'Jan de Vries',
            memberId: null,
            total: new CompoundPrice(100.0, 21.0),
            status: InvoiceStatus::Pending,
            sequenceType: SequenceType::Recurring,
        );

        return ExportReadinessIssue::forInvoice($reason, $candidate);
    }
}
