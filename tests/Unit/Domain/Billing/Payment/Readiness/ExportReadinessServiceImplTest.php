<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\CollectionReadinessAssessor;
use App\Domain\Billing\Payment\Readiness\ExportReadinessCategory;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReport;
use App\Domain\Billing\Payment\Readiness\ExportReadinessServiceImpl;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Billing\Payment\SepaBatchRepositoryExpectation;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\UnitTestCase;

final class ExportReadinessServiceImplTest extends UnitTestCase
{
    private const int BATCH_ID = 1;

    private SepaBatchRepositoryExpectation $repository;
    private ClockExpectation $clock;
    private MandateReadinessRepositoryExpectation $mandateReadiness;
    private ExportReadinessServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = SepaBatchRepositoryExpectation::create();
        $this->clock = ClockExpectation::create();
        $this->mandateReadiness = MandateReadinessRepositoryExpectation::create();

        $this->service = new ExportReadinessServiceImpl(
            $this->repository->mock,
            $this->configuration(),
            $this->clock->mock,
            $this->mandateReadiness->mock,
            new CollectionReadinessAssessor(),
        );
    }

    public function test_assess_reports_a_manual_payer_as_an_expected_exclusion(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => MandateReadiness::missing(PaymentMethod::Transfer)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::MissingMandate]);
        static::assertCount(1, $report->expectedExclusions());
        static::assertFalse($report->isBlocked());
    }

    public function test_assess_reports_a_direct_debit_member_without_a_mandate_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => MandateReadiness::missing(PaymentMethod::DirectDebit)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::MissingMandate]);
        static::assertCount(1, $report->brokenDataIssues());
        static::assertTrue($report->isBlocked());
    }

    public function test_assess_reports_a_revoked_mandate_of_a_transfer_member_as_an_expected_exclusion(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => new MandateReadiness(
                MandateReadinessStatus::Revoked,
                paymentMethod: PaymentMethod::Transfer,
            )],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::RevokedMandate]);
        static::assertFalse($report->isBlocked());
    }

    public function test_assess_reports_an_expired_mandate_as_overridable(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => $this->activeMandate(status: MandateReadinessStatus::Expired)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::ExpiredMandate]);
        static::assertCount(1, $report->overridableIssues());
    }

    public function test_assess_reports_a_missing_iban_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => $this->activeMandate(iban: null)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::MissingIban]);
        static::assertCount(1, $report->brokenDataIssues());
    }

    public function test_assess_reports_a_malformed_iban_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => $this->activeMandate(iban: 'NL91ABNA0417164301')],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::MalformedIban]);
    }

    public function test_assess_reports_a_zero_total_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(0.0, 0.0), $memberId)],
            [$memberId->value => $this->activeMandate()],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::ZeroAmount]);
    }

    public function test_assess_reports_an_open_invoice_in_a_pending_batch_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, InvoiceStatus::Open)],
            [$memberId->value => $this->activeMandate()],
            status: SepaBatchStatus::Pending,
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::UncollectableStatus]);
    }

    public function test_assess_reports_a_sequence_type_mismatch_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, sequenceType: SequenceType::First)],
            [$memberId->value => $this->activeMandate(expectedSequenceType: SequenceType::Recurring)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::SequenceTypeMismatch]);
        static::assertSame(ExportReadinessCategory::BrokenData, $report->brokenDataIssues()[0]->category());
        static::assertTrue($report->isBlocked());
    }

    public function test_assess_passes_a_legitimate_first_collection(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, sequenceType: SequenceType::First)],
            [$memberId->value => $this->activeMandate(expectedSequenceType: SequenceType::First)],
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_passes_a_legitimate_recurring_collection(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, sequenceType: SequenceType::Recurring)],
            [$memberId->value => $this->activeMandate(expectedSequenceType: SequenceType::Recurring)],
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_reports_a_stale_frst_plan_as_broken_data(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, sequenceType: SequenceType::First)],
            [$memberId->value => $this->activeMandate(expectedSequenceType: SequenceType::Recurring)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::SequenceTypeMismatch]);
        static::assertSame(ExportReadinessCategory::BrokenData, $report->brokenDataIssues()[0]->category());
        static::assertTrue($report->isBlocked());
    }

    public function test_assess_reports_a_stale_rcur_plan_after_reauthorisation(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, sequenceType: SequenceType::Recurring)],
            [$memberId->value => $this->activeMandate(expectedSequenceType: SequenceType::First)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::SequenceTypeMismatch]);
        static::assertTrue($report->isBlocked());
    }

    public function test_assess_does_not_report_the_sequence_of_a_presented_batch(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId, sequenceType: SequenceType::First)],
            [$memberId->value => $this->activeMandate(expectedSequenceType: SequenceType::Recurring)],
            submittedAt: '2026-10-23 09:30:00',
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_passes_an_active_mandate_with_a_valid_iban(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(100.0, 21.0), $memberId)],
            [$memberId->value => $this->activeMandate()],
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_reports_a_refund_without_an_iban_as_a_refund_exclusion(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(-100.0, -21.0), $memberId)],
            [$memberId->value => $this->activeMandate(iban: null)],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::RefundIbanUnavailable]);
        static::assertCount(1, $report->refundExclusions());
        static::assertFalse($report->isBlocked());
        static::assertTrue($report->requiresExclusionAcknowledgement());
    }

    public function test_assess_reports_a_malformed_refund_iban_as_a_refund_exclusion(): void
    {
        $memberId = MemberId::create(7);
        $this->expectAssessment(
            [$this->candidate(new CompoundPrice(-100.0, -21.0), $memberId)],
            [$memberId->value => $this->activeMandate(iban: 'NL91ABNA0417164301')],
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::RefundIbanUnavailable]);
        static::assertCount(1, $report->refundExclusions());
        static::assertFalse($report->isBlocked());
    }

    public function test_assess_blocks_one_day_inside_the_notice_period(): void
    {
        $this->expectAssessment(
            [],
            [],
            closedAt: '2026-10-01',
            sepaTransferDate: '2026-10-14',
        );

        $report = $this->service->assess($this->batchId());

        $this->assertReasons($report, [ExportReadinessReason::NoticePeriodTooShort]);
        static::assertCount(1, $report->batchLevelIssues());
    }

    public function test_assess_passes_exactly_on_the_notice_period_boundary(): void
    {
        $this->expectAssessment(
            [],
            [],
            closedAt: '2026-10-01',
            sepaTransferDate: '2026-10-15',
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_measures_from_the_close_moment_when_the_batch_is_closed(): void
    {
        $this->expectAssessment(
            [],
            [],
            closedAt: '2026-10-01',
            sepaTransferDate: '2026-10-15',
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_falls_back_to_the_clock_for_an_open_batch(): void
    {
        $this->clock->expectsNow(CarbonImmutable::parse('2026-10-01 10:00:00'));

        $this->expectAssessment(
            [],
            [],
            status: SepaBatchStatus::Open,
            closedAt: null,
            sepaTransferDate: '2026-10-15',
        );

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
    }

    public function test_assess_returns_no_issues_for_an_empty_batch(): void
    {
        $this->expectAssessment([], []);

        $report = $this->service->assess($this->batchId());

        static::assertTrue($report->isEmpty());
        static::assertFalse($report->isBlocked());
    }

    /**
     * @param list<SepaCollectionCandidate> $candidates
     * @param array<int, MandateReadiness> $mandates
     */
    private function expectAssessment(
        array $candidates,
        array $mandates,
        SepaBatchStatus $status = SepaBatchStatus::Pending,
        string $sepaTransferDate = '2026-11-05',
        ?string $closedAt = '2026-10-22',
        ?string $submittedAt = null,
    ): void {
        $batchId = $this->batchId();

        $this->repository->expectsGetExportContext($batchId, new SepaBatchExportContext(
            status: $status,
            sepaTransferDate: CarbonImmutable::parse($sepaTransferDate),
            closedAt: $closedAt === null ? null : CarbonImmutable::parse($closedAt),
            submittedAt: $submittedAt === null ? null : CarbonImmutable::parse($submittedAt),
        ));

        $this->repository->expectsGetCollectionCandidates($batchId, $candidates);

        $memberIds = [];

        foreach ($candidates as $candidate) {
            if ($candidate->memberId === null) {
                continue;
            }

            $memberIds[$candidate->memberId->value] = $candidate->memberId;
        }

        $this->mandateReadiness->expectsForMembers(array_values($memberIds), $mandates);
    }

    private function batchId(): SepaBatchId
    {
        return SepaBatchId::create(self::BATCH_ID);
    }

    private function configuration(): SepaConfiguration
    {
        return new SepaConfiguration(
            creditorId: 'NL12ZZZ1234567890',
            creditorName: 'Test Club',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
            noticePeriodDays: 14,
        );
    }

    private function candidate(
        CompoundPrice $total,
        ?MemberId $memberId = null,
        InvoiceStatus $status = InvoiceStatus::Pending,
        SequenceType $sequenceType = SequenceType::Recurring,
    ): SepaCollectionCandidate {
        return new SepaCollectionCandidate(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'I-2026-000001',
            recipientName: 'Jan de Vries',
            memberId: $memberId,
            total: $total,
            status: $status,
            sequenceType: $sequenceType,
        );
    }

    private function activeMandate(
        ?string $iban = 'NL91ABNA0417164300',
        SequenceType $expectedSequenceType = SequenceType::Recurring,
        MandateReadinessStatus $status = MandateReadinessStatus::Active,
    ): MandateReadiness {
        return new MandateReadiness(
            status: $status,
            iban: $iban,
            bic: 'ABNANL2A',
            expectedSequenceType: $expectedSequenceType,
        );
    }

    /** @param list<ExportReadinessReason> $expected */
    private function assertReasons(ExportReadinessReport $report, array $expected): void
    {
        static::assertSame(
            $expected,
            array_map(static fn (ExportReadinessIssue $issue): ExportReadinessReason => $issue->reason, $report->issues),
        );
    }
}
