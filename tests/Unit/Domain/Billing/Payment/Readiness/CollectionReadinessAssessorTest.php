<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\CollectionReadinessAssessor;
use App\Domain\Billing\Payment\Readiness\ExportReadinessCategory;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;
use Tests\UnitTestCase;

final class CollectionReadinessAssessorTest extends UnitTestCase
{
    private CollectionReadinessAssessor $assessor;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->assessor = new CollectionReadinessAssessor();
    }

    public function test_it_reports_an_open_candidate_on_a_pending_batch_as_uncollectable(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(InvoiceStatus::Open),
            $this->mandate(),
            $this->context(),
        );

        static::assertSame([ExportReadinessReason::UncollectableStatus], $this->reasons($issues));
    }

    public function test_it_reports_a_missing_mandate_as_broken_data(): void
    {
        $issues = $this->assessor->assess($this->candidate(), null, $this->context());

        static::assertSame([ExportReadinessReason::MissingMandate], $this->reasons($issues));
        static::assertSame(ExportReadinessCategory::BrokenData, $issues[0]->category());
    }

    public function test_it_reports_a_revoked_mandate_as_broken_data(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(),
            new MandateReadiness(MandateReadinessStatus::Revoked),
            $this->context(),
        );

        static::assertSame([ExportReadinessReason::RevokedMandate], $this->reasons($issues));
        static::assertSame(ExportReadinessCategory::BrokenData, $issues[0]->category());
    }

    public function test_it_reports_an_expired_mandate_and_a_sequence_mismatch_together(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(sequenceType: SequenceType::First),
            new MandateReadiness(
                status: MandateReadinessStatus::Expired,
                iban: 'NL91ABNA0417164300',
                expectedSequenceType: SequenceType::Recurring,
            ),
            $this->context(),
        );

        static::assertSame(
            [ExportReadinessReason::ExpiredMandate, ExportReadinessReason::SequenceTypeMismatch],
            $this->reasons($issues),
        );
    }

    public function test_it_skips_the_sequence_check_for_a_presented_batch(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(sequenceType: SequenceType::First),
            new MandateReadiness(
                status: MandateReadinessStatus::Active,
                iban: 'NL91ABNA0417164300',
                expectedSequenceType: SequenceType::Recurring,
            ),
            $this->context(submittedAt: CarbonImmutable::parse('2026-10-23 09:30:00')),
        );

        static::assertSame([], $issues);
    }

    public function test_it_passes_a_refund_with_a_known_iban(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(total: new CompoundPrice(-10.0, -2.1), sequenceType: SequenceType::Recurring),
            new MandateReadiness(status: MandateReadinessStatus::Active, iban: 'NL91ABNA0417164300'),
            $this->context(),
        );

        static::assertSame([], $issues);
    }

    public function test_it_reports_a_refund_without_an_iban_as_broken_data(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(total: new CompoundPrice(-10.0, -2.1), sequenceType: SequenceType::Recurring),
            new MandateReadiness(status: MandateReadinessStatus::Active, iban: null),
            $this->context(),
        );

        static::assertSame([ExportReadinessReason::MissingIban], $this->reasons($issues));
        static::assertSame(ExportReadinessCategory::BrokenData, $issues[0]->category());
    }

    public function test_it_reports_a_refund_without_any_mandate_as_broken_data(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(total: new CompoundPrice(-10.0, -2.1), sequenceType: SequenceType::Recurring),
            null,
            $this->context(),
        );

        static::assertSame([ExportReadinessReason::MissingIban], $this->reasons($issues));
        static::assertSame(ExportReadinessCategory::BrokenData, $issues[0]->category());
    }

    public function test_it_reports_a_refund_with_a_malformed_iban_as_broken_data(): void
    {
        $issues = $this->assessor->assess(
            $this->candidate(total: new CompoundPrice(-10.0, -2.1), sequenceType: SequenceType::Recurring),
            new MandateReadiness(status: MandateReadinessStatus::Revoked, iban: 'NOT-AN-IBAN'),
            $this->context(),
        );

        static::assertSame([ExportReadinessReason::MalformedIban], $this->reasons($issues));
        static::assertSame(ExportReadinessCategory::BrokenData, $issues[0]->category());
    }

    private function candidate(
        InvoiceStatus $status = InvoiceStatus::Pending,
        CompoundPrice $total = new CompoundPrice(10.0, 2.1),
        SequenceType $sequenceType = SequenceType::Recurring,
    ): SepaCollectionCandidate {
        return new SepaCollectionCandidate(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'I-2026-000001',
            recipientName: 'Jan de Vries',
            memberId: MemberId::create(7),
            total: $total,
            status: $status,
            sequenceType: $sequenceType,
        );
    }

    private function mandate(): MandateReadiness
    {
        return new MandateReadiness(
            status: MandateReadinessStatus::Active,
            iban: 'NL91ABNA0417164300',
            expectedSequenceType: SequenceType::Recurring,
        );
    }

    private function context(?DateTimeInterface $submittedAt = null): SepaBatchExportContext
    {
        return new SepaBatchExportContext(
            status: SepaBatchStatus::Pending,
            sepaTransferDate: CarbonImmutable::parse('2026-11-05'),
            closedAt: CarbonImmutable::parse('2026-10-22'),
            submittedAt: $submittedAt,
        );
    }

    /** @param list<ExportReadinessIssue> $issues */
    private function reasons(array $issues): array
    {
        return array_map(
            static fn (ExportReadinessIssue $issue): ExportReadinessReason => $issue->reason,
            $issues,
        );
    }
}
