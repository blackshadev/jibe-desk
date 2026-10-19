<?php

declare(strict_types=1);

namespace App\Domain\Billing\PurchaseOrders;

use App\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepository;
use Override;

final readonly class PurchaseOrderServiceImpl implements PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderCompletenessService $completenessService,
        private PurchaseOrderRepository $repository,
        private BookkeepingRecordRepository $bookkeepingRepository,
    ) {}

    #[Override]
    public function acceptApproval(PurchaseOrderIdList $ids): void
    {
        $this->completenessService->findProblems($ids)->assertComplete();

        $this->repository->markAsApprovalAccepted($ids);
    }

    #[Override]
    public function declineApproval(PurchaseOrderIdList $ids, ?string $reason = null): void
    {
        $this->repository->markAsApprovalDeclined($ids, $reason);
    }

    #[Override]
    public function markAsPaymentPending(PurchaseOrderIdList $ids): void
    {
        $this->completenessService->findProblems($ids)->assertComplete();

        $this->repository->markAsPaymentPending($ids);
        $this->bookkeepingRepository->createForPurchaseOrder($ids);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderIdList $ids): void
    {
        foreach ($ids->ids as $id) {
            if (!$this->repository->hasCompletedTransactions($id)) {
                throw new PurchaseOrderHasNoCompletedTransactionsException($id);
            }
        }

        $this->completenessService->findProblems($ids)->assertComplete();

        $this->repository->markAsPaid($ids);
        $this->bookkeepingRepository->createForPurchaseOrder($ids);
    }

    #[Override]
    public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
    {
        $this->repository->markAsPaymentDeclined($ids, $reason);
    }
}
