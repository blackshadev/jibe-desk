<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderHasNoCompletedTransactionsException;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderProblems;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderProblemType;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderServiceImpl;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepositoryExpectation;
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

    public function test_accept_approval_updates_approval_without_creating_bookkeeping_records(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems());
        $this->repo->expectsMarkAsApprovalAccepted($ids);
        $this->repo->neverExpectsMarkAsPaymentPending();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->service->acceptApproval($ids);
    }

    public function test_accept_approval_applies_assert_completeness(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems([$problem]));
        $this->repo->neverExpectsMarkAsApprovalAccepted();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderIncompleteException::class);

        $this->service->acceptApproval($ids);
    }

    public function test_decline_approval_stores_reason_on_repository(): void
    {
        $ids = PurchaseOrderIdList::fromArray([1]);
        $this->repo->expectsMarkAsApprovalDeclined($ids, 'Niet geautoriseerd door penningmeester');

        $this->service->declineApproval($ids, 'Niet geautoriseerd door penningmeester');
    }

    public function test_decline_approval_without_reason_passes_null(): void
    {
        $ids = PurchaseOrderIdList::fromArray([1]);
        $this->repo->expectsMarkAsApprovalDeclined($ids, null);

        $this->service->declineApproval($ids);
    }

    public function test_mark_as_payment_pending_updates_status_and_creates_bookkeeping_records(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems());
        $this->repo->expectsMarkAsPaymentPending($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPaymentPending($ids);
    }

    public function test_mark_as_payment_pending_applies_assert_completeness(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems([$problem]));
        $this->repo->neverExpectsMarkAsPaymentPending();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderIncompleteException::class);

        $this->service->markAsPaymentPending($ids);
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(2)]);
        $this->repo->expectsHasCompletedTransactions($ids->ids[0]);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems());
        $this->repo->expectsMarkAsPaid($ids);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($ids);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_paid_is_blocked_without_completed_transactions(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(2)]);
        $this->repo->expectsHasCompletedTransactions($ids->ids[0], false);
        $this->completenessService->neverExpectsFindProblems();
        $this->repo->neverExpectsMarkAsPaid();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderHasNoCompletedTransactionsException::class);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_paid_applies_assert_completeness(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1);
        $this->repo->expectsHasCompletedTransactions($ids->ids[0]);
        $this->completenessService->expectsFindProblems($ids, new PurchaseOrderProblems([$problem]));
        $this->repo->neverExpectsMarkAsPaid();
        $this->bookkeepingRepo->neverExpectsCreateForPurchaseOrder();

        $this->expectException(PurchaseOrderIncompleteException::class);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_payment_declined_stores_reason_on_repository(): void
    {
        $ids = PurchaseOrderIdList::fromArray([1]);
        $this->repo->expectsMarkAsPaymentDeclined($ids, 'Niet geautoriseerd door penningmeester');

        $this->service->markAsPaymentDeclined($ids, 'Niet geautoriseerd door penningmeester');
    }

    public function test_mark_as_payment_declined_without_reason_passes_null(): void
    {
        $ids = PurchaseOrderIdList::fromArray([1]);
        $this->repo->expectsMarkAsPaymentDeclined($ids, null);

        $this->service->markAsPaymentDeclined($ids);
    }
}
