<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderCompletenessServiceImpl;
use App\Domain\PurchaseOrders\PurchaseOrderHasNoCompletedTransactionsException;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderLineCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderServiceImpl;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;
use Tests\UnitTestCase;

final class PurchaseOrderServiceTest extends UnitTestCase
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
            new PurchaseOrderCompletenessServiceImpl($this->repo->mock),
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
        );
    }

    public function test_mark_as_approved_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = PurchaseOrderId::create(1);
        $ids = new PurchaseOrderIdList([$id]);

        $this->repo->expectsGetCompleteness($ids, [
            new PurchaseOrderCompleteness(
                $id,
                'Creditor Name',
                'NL91ABNA0417164300',
                [new PurchaseOrderLineCompleteness(1)],
            ),
        ]);
        $this->repo->expectsMarkAsApproved($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsApproved($ids);
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = PurchaseOrderId::create(2);
        $ids = new PurchaseOrderIdList([$id]);

        $this->repo->expectsGetCompleteness($ids, [
            new PurchaseOrderCompleteness(
                $id,
                'Creditor Name',
                'NL91ABNA0417164300',
                [new PurchaseOrderLineCompleteness(1)],
            ),
        ]);
        $this->repo->expectsHasCompletedTransactions($ids->ids[0]);
        $this->repo->expectsMarkAsPaid($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_paid_is_blocked_without_completed_transactions(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(2)]);

        $this->repo->expectsHasCompletedTransactions($ids->ids[0], false);
        $this->repo->neverExpectsMarkAsPaid();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderHasNoCompletedTransactionsException::class);

        $this->service->markAsPaid($ids);
    }
}
