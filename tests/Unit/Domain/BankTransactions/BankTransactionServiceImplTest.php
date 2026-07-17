<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionServiceImpl;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use Override;
use Tests\Unit\Domain\Invoices\InvoiceServiceExpectation;
use Tests\Unit\Domain\PurchaseOrders\PurchaseOrderServiceExpectation;
use Tests\FeatureTestCase;

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

    public function test_attach_invoice_marks_as_paid_then_attaches(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $invoiceId = InvoiceId::create(2);

        $this->invoiceService->expectsMarkAsPaid($invoiceId);
        $this->repo->expectsAttachInvoice($bankTransactionId, $invoiceId);

        $this->service->attachInvoice($bankTransactionId, $invoiceId);
    }

    public function test_attach_purchase_order_marks_as_paid_then_attaches(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $purchaseOrderId = PurchaseOrderId::create(3);

        $this->purchaseOrderService->expectsMarkAsPaid($purchaseOrderId);
        $this->repo->expectsAttachPurchaseOrder($bankTransactionId, $purchaseOrderId);

        $this->service->attachPurchaseOrder($bankTransactionId, $purchaseOrderId);
    }
}
