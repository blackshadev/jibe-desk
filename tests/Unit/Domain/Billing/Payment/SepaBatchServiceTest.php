<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Payment\Events\SepaBatchClosed;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReport;
use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchServiceImpl;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SepaExport;
use Carbon\CarbonImmutable;
use DomainException;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepositoryExpectation;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class SepaBatchServiceTest extends UnitTestCase
{
    private SepaBatchRepositoryExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private EventDispatcherExpectation $dispatcher;
    private SepaExportServiceExpectation $exportService;
    private ExportReadinessServiceExpectation $readinessService;
    private ClockExpectation $clock;
    private MandateRepositoryExpectation $mandateRepo;
    private SepaBatchServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = SepaBatchRepositoryExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();
        $this->dispatcher = EventDispatcherExpectation::create();
        $this->exportService = SepaExportServiceExpectation::create();
        $this->readinessService = ExportReadinessServiceExpectation::create();
        $this->clock = ClockExpectation::create();
        $this->mandateRepo = MandateRepositoryExpectation::create();

        $this->service = new SepaBatchServiceImpl(
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
            $this->dispatcher->mock,
            $this->exportService->mock,
            $this->readinessService->mock,
            $this->clock->mock,
            $this->mandateRepo->mock,
        );
    }

    public function test_create_batch(): void
    {
        $invoiceDate = CarbonImmutable::parse('2026-05-15');
        $sepaTransferDate = CarbonImmutable::parse('2026-05-29');
        $expectedId = SepaBatchId::create(1);

        $this->repo->expectsCreate($invoiceDate, $sepaTransferDate, SepaBatchStatus::Open, $expectedId);

        $result = $this->service->createBatch($invoiceDate, $sepaTransferDate);

        static::assertSame($expectedId, $result);
    }

    public function test_attach_batch_month(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsAddOpenInvoicesFromBatchMonth($batchId, 3);

        static::assertSame(3, $this->service->attachBatchMonthInvoices($batchId));
    }

    public function test_start_generation(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsMarkGenerationStarted($batchId, 25);

        $this->service->startGeneration($batchId, 25);
    }

    public function test_finish_generation(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsMarkGenerationFinished($batchId);

        $this->service->finishGeneration($batchId);
    }

    public function test_close_batch(): void
    {
        $batchId = SepaBatchId::create(5);

        $this->repo->expectsMarkInvoicesAsPending($batchId);
        $this->repo->expectsFreezeSequenceTypes($batchId);
        $this->bookkeepingRepo->expectsCreateForBatch($batchId);
        $this->repo->expectsSettleCreditPairs($batchId);
        $this->repo->expectsCloseBatch($batchId);

        $this->dispatcher->expectsDispatch(new SepaBatchClosed(batchId: $batchId));

        $this->service->closeBatch($batchId);
    }

    public function test_complete_batch(): void
    {
        $batchId = SepaBatchId::create(5);

        $this->repo->expectsCompleteBatch($batchId);

        $this->service->completeBatch($batchId);
    }

    public function test_attach_invoices(): void
    {
        $batchId = SepaBatchId::create(5);
        $ids = InvoiceIdList::fromArray([1, 2]);

        $this->repo->expectsAttachInvoices($batchId, $ids, 2);

        static::assertSame(2, $this->service->attachInvoices($batchId, $ids));
    }

    public function test_export_throws_when_readiness_is_not_satisfied(): void
    {
        $batchId = SepaBatchId::create(5);
        $confirmation = new ExportConfirmation();
        $report = $this->blockedReport($batchId);

        $this->repo->expectsGetExportContext($batchId, $this->pendingContext());
        $this->readinessService->expectsAssess($batchId, $report);

        $this->expectException(ExportNotReadyException::class);

        $this->service->export($batchId, $confirmation, 1);
    }

    public function test_export_does_not_generate_or_stamp_when_not_ready(): void
    {
        $batchId = SepaBatchId::create(5);
        $confirmation = new ExportConfirmation();
        $report = $this->blockedReport($batchId);

        $this->repo->expectsGetExportContext($batchId, $this->pendingContext());
        $this->readinessService->expectsAssess($batchId, $report);
        $this->exportService->neverExpectsExport();
        $this->repo->neverExpectsRecordExport();

        $this->expectException(ExportNotReadyException::class);

        $this->service->export($batchId, $confirmation, 1);
    }

    public function test_export_records_the_export_and_returns_the_files(): void
    {
        $batchId = SepaBatchId::create(5);
        $confirmation = new ExportConfirmation();
        $now = CarbonImmutable::parse('2026-10-22 12:00:00');
        $export = new SepaExport(creditTransfers: '', directDebit: '<xml/>');

        $this->repo->expectsGetExportContext($batchId, $this->pendingContext());
        $this->readinessService->expectsAssess($batchId, $this->readyReport($batchId));
        $this->repo->expectsFreezeSequenceTypes($batchId);
        $this->exportService->expectsExport($batchId, $export);
        $this->clock->expectsNow($now);
        $this->repo->expectsRecordExport($batchId, $confirmation, 7, $now);

        static::assertSame($export, $this->service->export($batchId, $confirmation, 7));
    }

    public function test_export_rejects_a_batch_that_is_not_pending(): void
    {
        $batchId = SepaBatchId::create(5);
        $confirmation = new ExportConfirmation();

        $this->repo->expectsGetExportContext($batchId, new SepaBatchExportContext(
            status: SepaBatchStatus::Open,
            sepaTransferDate: CarbonImmutable::parse('2026-11-05'),
            closedAt: null,
        ));
        $this->readinessService->neverExpectsAssess();
        $this->exportService->neverExpectsExport();

        $this->expectException(DomainException::class);

        $this->service->export($batchId, $confirmation, 1);
    }

    public function test_replan_sequence_types_delegates_to_the_repository(): void
    {
        $batchId = SepaBatchId::create(5);

        $this->repo->expectsReplanSequenceTypes($batchId);

        $this->service->replanSequenceTypes($batchId);
    }

    public function test_mark_submitted_stamps_the_user_and_time(): void
    {
        $batchId = SepaBatchId::create(5);
        $now = CarbonImmutable::parse('2026-10-23 09:30:00');

        $this->clock->expectsNow($now);
        $this->repo->expectsMarkSubmitted($batchId, 7, $now);
        $this->mandateRepo->expectsStampCollectionsForBatch($batchId, $now);

        $this->service->markSubmitted($batchId, 7);
    }

    private function pendingContext(): SepaBatchExportContext
    {
        return new SepaBatchExportContext(
            status: SepaBatchStatus::Pending,
            sepaTransferDate: CarbonImmutable::parse('2026-11-05'),
            closedAt: CarbonImmutable::parse('2026-10-22'),
        );
    }

    private function readyReport(SepaBatchId $batchId): ExportReadinessReport
    {
        return new ExportReadinessReport(batchId: $batchId, issues: [], noticePeriodDays: 14);
    }

    private function blockedReport(SepaBatchId $batchId): ExportReadinessReport
    {
        return new ExportReadinessReport(
            batchId: $batchId,
            issues: [
                ExportReadinessIssue::forBatch(
                    ExportReadinessReason::NoticePeriodTooShort,
                    '3 dagen tussen sluiting en incassodatum; minimaal 14.',
                ),
            ],
            noticePeriodDays: 14,
        );
    }
}
