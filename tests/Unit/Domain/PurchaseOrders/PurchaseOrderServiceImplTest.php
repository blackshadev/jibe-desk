<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderServiceImpl;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;
use Tests\UnitTestCase;

final class PurchaseOrderServiceImplTest extends UnitTestCase
{
    private PurchaseOrderRepositoryExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private PurchaseOrderServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = PurchaseOrderRepositoryExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();

        $this->service = new PurchaseOrderServiceImpl(
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
        );
    }

    public function test_mark_as_pending_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = PurchaseOrderId::create(1);
        $ids = new PurchaseOrderIdList([$id]);

        $this->repo->expectsMarkAsPending($id);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPending($id);
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = PurchaseOrderId::create(2);
        $ids = new PurchaseOrderIdList([$id]);

        $this->repo->expectsMarkAsPaid($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPaid($ids);
    }
}
