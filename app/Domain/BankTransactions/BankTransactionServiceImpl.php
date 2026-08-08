<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceService;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use Override;

final readonly class BankTransactionServiceImpl implements BankTransactionService
{
    public function __construct(
        private BankTransactionRepository $repository,
        private InvoiceService $invoiceService,
        private PurchaseOrderService $purchaseOrderService,
        private TransactionMatchingService $matchingService,
    ) {}

    #[Override]
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        $this->repository->attachInvoice($bankTransactionId, $invoiceId);
    }

    #[Override]
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        $this->repository->attachPurchaseOrder($bankTransactionId, $purchaseOrderId);
    }

    #[Override]
    public function complete(BankTransactionId $bankTransactionId): void
    {
        $invoiceIdList = $this->repository->getAttachedInvoiceIds($bankTransactionId);
        $purchaseOrderIdList = $this->repository->getAttachedPurchaseOrderIds($bankTransactionId);

        $this->invoiceService->markAsPaid($invoiceIdList);
        $this->purchaseOrderService->markAsPaid($purchaseOrderIdList);

        $this->repository->complete($bankTransactionId);
    }

    #[Override]
    public function resolveMatching(BankTransactionIdList $ids): void
    {
        $criteriaList = $this->repository->getMatchCriteriaForIds($ids);

        foreach ($criteriaList as $bankTransactionId => $criteria) {
            $result = $this->matchingService->findMatch($criteria);
            $id = BankTransactionId::create($bankTransactionId);

            if (!$result->isMatch) {
                $this->repository->markAsUnresolvable($id);
                continue;
            }

            if ($result->invoiceId !== null) {
                $this->repository->attachInvoice($id, $result->invoiceId);
            }
            if ($result->purchaseOrderId !== null) {
                $this->repository->attachPurchaseOrder($id, $result->purchaseOrderId);
            }
            if ($result->reversedByTransactionId !== null) {
                $this->linkReversal($result->reversedByTransactionId, $id);
            }

            $this->repository->markAsResolved($id);
        }
    }

    #[Override]
    public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
    {
        $this->repository->linkReversal($reversalId, $originalId);

        $invoiceIds = $this->repository->getAttachedInvoiceIds($originalId);
        $this->invoiceService->markAsDeclined($invoiceIds);

        $purchaseOrderIds = $this->repository->getAttachedPurchaseOrderIds($originalId);
        $this->purchaseOrderService->markAsDeclined($purchaseOrderIds);

        $this->repository->markAsResolved($reversalId);
        $this->repository->markAsResolved($originalId);
    }

    #[Override]
    public function unlinkReversal(BankTransactionId $reversalId): void
    {
        $invoiceIds = $this->repository->getAttachedInvoiceIds($reversalId);
        $this->invoiceService->markAsPending($invoiceIds);

        $purchaseOrderIds = $this->repository->getAttachedPurchaseOrderIds($reversalId);
        $this->purchaseOrderService->markAsPending($purchaseOrderIds);

        $this->repository->unlinkReversal($reversalId);
    }
}
