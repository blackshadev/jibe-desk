<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Events\SepaBatchClosed;
use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchServiceImpl;
use App\Domain\Invoices\SepaBatchStatus;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class SepaBatchServiceTest extends UnitTestCase
{
    private SepaBatchRepositoryExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private EventDispatcherExpectation $dispatcher;
    private SepaBatchServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = SepaBatchRepositoryExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();
        $this->dispatcher = EventDispatcherExpectation::create();

        $this->service = new SepaBatchServiceImpl(
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
            $this->dispatcher->mock,
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
        $this->bookkeepingRepo->expectsCreateForBatch($batchId);
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
}
