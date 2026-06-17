<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Events\InvoiceBatchClosed;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchService;
use App\Domain\Invoices\InvoiceBatchStatus;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class InvoiceBatchServiceTest extends UnitTestCase
{
    private InvoiceBatchRepositoryExpectation $repo;
    private EventDispatcherExpectation $dispatcher;
    private InvoiceBatchService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = InvoiceBatchRepositoryExpectation::create();
        $this->dispatcher = EventDispatcherExpectation::create();

        $this->service = new InvoiceBatchService($this->repo->mock, $this->dispatcher->mock);
    }

    public function testCreateBatch(): void
    {
        $invoiceDate = CarbonImmutable::parse('2026-05-15');
        $expectedId = InvoiceBatchId::create(1);

        $this->repo->expectsCreate($invoiceDate, InvoiceBatchStatus::Open, $expectedId);

        $result = $this->service->createBatch($invoiceDate);

        static::assertSame($expectedId, $result);
    }

    public function testAttachBatchMonth(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsAddOpenInvoicesFromBatchMonth($batchId);

        $this->service->attachBatchMonth($batchId);
    }

    public function testCloseBatch(): void
    {
        $batchId = InvoiceBatchId::create(5);

        $this->repo->expectsMarkInvoicesAsPending($batchId);
        $this->repo->expectsCloseBatch($batchId);

        $this->dispatcher->expectsDispatch(new InvoiceBatchClosed(batchId: $batchId));

        $this->service->closeBatch($batchId);
    }

    public function testCompleteBatch(): void
    {
        $batchId = InvoiceBatchId::create(5);

        $this->repo->expectsCompleteBatch($batchId);

        $this->service->completeBatch($batchId);
    }
}
