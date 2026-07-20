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
    public function markAsPending(PurchaseOrderId $id): void
    {
        $this->repository->markAsPending($id);
        $this->bookkeepingRepository->createForPurchaseOrder(new PurchaseOrderIdList([$id]));
    }

    #[Override]
    public function markAsPaid(PurchaseOrderIdList $ids): void
    {
        $this->repository->markAsPaid($ids);
        $this->bookkeepingRepository->createForPurchaseOrder($ids);
    }
}
