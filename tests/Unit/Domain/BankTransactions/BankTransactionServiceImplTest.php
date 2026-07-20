<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionServiceImpl;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use Override;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\Invoices\InvoiceServiceExpectation;
use Tests\Unit\Domain\PurchaseOrders\PurchaseOrderServiceExpectation;

final class BankTransactionServiceImplTest extends FeatureTestCase
{
    private BankTransactionRepositoryExpectation $repo;
    private InvoiceServiceExpectation $invoiceService;
    private PurchaseOrderServiceExpectation $purchaseOrderService;
    private BankTransactionServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = BankTransactionRepositoryExpectation::create();
        $this->invoiceService = InvoiceServiceExpectation::create();
        $this->purchaseOrderService = PurchaseOrderServiceExpectation::create();

        $this->service = new BankTransactionServiceImpl(
            $this->repo->mock,
            $this->invoiceService->mock,
            $this->purchaseOrderService->mock,
        );
    }

    public function test_attach_invoice_does_not_mark_as_paid(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $invoiceId = InvoiceId::create(2);

        $this->repo->expectsAttachInvoice($bankTransactionId, $invoiceId);

        $this->service->attachInvoice($bankTransactionId, $invoiceId);
    }

    public function test_attach_purchase_order_does_not_mark_as_paid(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $purchaseOrderId = PurchaseOrderId::create(3);

        $this->repo->expectsAttachPurchaseOrder($bankTransactionId, $purchaseOrderId);

        $this->service->attachPurchaseOrder($bankTransactionId, $purchaseOrderId);
    }

    public function test_complete_marks_as_paid_and_completes(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $invoiceId = InvoiceId::create(2);
        $purchaseOrderId = PurchaseOrderId::create(3);

        $invoiceIdList = new InvoiceIdList([$invoiceId]);
        $purchaseOrderIdList = new PurchaseOrderIdList([$purchaseOrderId]);

        $this->repo->expectsGetAttachedInvoiceIds($bankTransactionId, $invoiceIdList);
        $this->repo->expectsGetAttachedPurchaseOrderIds($bankTransactionId, $purchaseOrderIdList);
        $this->invoiceService->expectsMarkAsPaid($invoiceIdList);
        $this->purchaseOrderService->expectsMarkAsPaid($purchaseOrderIdList);
        $this->repo->expectsComplete($bankTransactionId);

        $this->service->complete($bankTransactionId);
    }
}
