<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblems;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use App\Domain\PurchaseOrders\PurchaseOrderServiceImpl;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;
use Tests\UnitTestCase;

final class PurchaseOrderServiceImplTest extends UnitTestCase
{
    private PurchaseOrderCompletenessServiceExpectation $completenessService;
    private PurchaseOrderRepositoryExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private PurchaseOrderServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->completenessService = PurchaseOrderCompletenessServiceExpectation::create();
        $this->repo = PurchaseOrderRepositoryExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();
        $this->service = new PurchaseOrderServiceImpl(
            $this->completenessService->mock,
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
        );
    }

    public function test_mark_as_pending_updates_status_and_creates_bookkeeping_records(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems());
        $this->repo->expectsMarkAsPending($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPending($ids);
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(2)]);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems());
        $this->repo->expectsMarkAsPaid($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_pending_applies_assert_completeness(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems([$problem]));
        $this->repo->neverExpectsMarkAsPending();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderIncompleteException::class);

        $this->service->markAsPending($ids);
    }

    public function test_mark_as_paid_applies_assert_completeness(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems([$problem]));
        $this->repo->neverExpectsMarkAsPaid();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderIncompleteException::class);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_declined_stores_reason_on_repository(): void
    {
        $ids = PurchaseOrderIdList::fromArray([1]);
        $this->repo->expectsMarkAsDeclined($ids, 'Niet geautoriseerd door penningmeester');

        $this->service->markAsDeclined($ids, 'Niet geautoriseerd door penningmeester');
    }

    public function test_mark_as_declined_without_reason_passes_null(): void
    {
        $ids = PurchaseOrderIdList::fromArray([1]);
        $this->repo->expectsMarkAsDeclined($ids, null);

        $this->service->markAsDeclined($ids);
    }
}
