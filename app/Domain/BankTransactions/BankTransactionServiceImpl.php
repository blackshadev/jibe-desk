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
}
