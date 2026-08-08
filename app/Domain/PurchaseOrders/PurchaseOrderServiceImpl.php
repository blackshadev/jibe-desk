<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use Override;

final readonly class PurchaseOrderServiceImpl implements PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderRepository $repository,
        private BookkeepingRecordRepository $bookkeepingRepository,
    ) {}

    #[Override]
    public function markAsPending(PurchaseOrderIdList $ids): void
    {
        $this->repository->markAsPending($ids);
        $this->bookkeepingRepository->createForPurchaseOrder($ids);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderIdList $ids): void
    {
        $this->repository->markAsPaid($ids);
        $this->bookkeepingRepository->createForPurchaseOrder($ids);
    }

    #[Override]
    public function markAsDeclined(PurchaseOrderIdList $ids): void
    {
        $this->repository->markAsDeclined($ids);
    }
}
