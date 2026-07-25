<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionServiceImpl;
use App\Domain\BankTransactions\MatchCriteria;
use App\Domain\BankTransactions\MatchResult;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use DateTimeImmutable;
use Override;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\Invoices\InvoiceServiceExpectation;
use Tests\Unit\Domain\PurchaseOrders\PurchaseOrderServiceExpectation;

final class BankTransactionServiceImplTest extends FeatureTestCase
{
    private BankTransactionRepositoryExpectation $repo;
    private InvoiceServiceExpectation $invoiceService;
    private PurchaseOrderServiceExpectation $purchaseOrderService;
    private TransactionMatchingServiceExpectation $matchingService;
    private BankTransactionServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = BankTransactionRepositoryExpectation::create();
        $this->invoiceService = InvoiceServiceExpectation::create();
        $this->purchaseOrderService = PurchaseOrderServiceExpectation::create();
        $this->matchingService = TransactionMatchingServiceExpectation::create();

        $this->service = new BankTransactionServiceImpl(
            $this->repo->mock,
            $this->invoiceService->mock,
            $this->purchaseOrderService->mock,
            $this->matchingService->mock,
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

    public function test_resolve_matching_with_match_calls_attach_and_mark_resolved(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $ids = new BankTransactionIdList([$bankTransactionId]);

        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
        );

        $invoiceId = InvoiceId::create(42);
        $result = MatchResult::foundInvoice($invoiceId);

        $this->repo->expectsGetMatchCriteriaForIds($ids, [1 => $criteria]);
        $this->matchingService->expectsFindMatch($criteria, $result);
        $this->repo->expectsAttachInvoice($bankTransactionId, $invoiceId);
        $this->repo->expectsMarkAsResolved($bankTransactionId);

        $this->service->resolveMatching($ids);
    }

    public function test_resolve_matching_without_match_marks_unresolvable(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $ids = new BankTransactionIdList([$bankTransactionId]);

        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
        );

        $result = MatchResult::none();

        $this->repo->expectsGetMatchCriteriaForIds($ids, [1 => $criteria]);
        $this->matchingService->expectsFindMatch($criteria, $result);
        $this->repo->expectsMarkAsUnresolvable($bankTransactionId);

        $this->service->resolveMatching($ids);
    }

    public function test_resolve_matching_with_purchase_order_match(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $ids = new BankTransactionIdList([$bankTransactionId]);

        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
        );

        $purchaseOrderId = PurchaseOrderId::create(7);
        $result = MatchResult::foundPurchaseOrder($purchaseOrderId);

        $this->repo->expectsGetMatchCriteriaForIds($ids, [1 => $criteria]);
        $this->matchingService->expectsFindMatch($criteria, $result);
        $this->repo->expectsAttachPurchaseOrder($bankTransactionId, $purchaseOrderId);
        $this->repo->expectsMarkAsResolved($bankTransactionId);

        $this->service->resolveMatching($ids);
    }

    public function test_resolve_matching_with_multiple_ids(): void
    {
        $id1 = BankTransactionId::create(1);
        $id2 = BankTransactionId::create(2);
        $ids = new BankTransactionIdList([$id1, $id2]);

        $criteria1 = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
        );

        $criteria2 = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-16'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164301',
        );

        $invoiceId = InvoiceId::create(42);
        $result1 = MatchResult::foundInvoice($invoiceId);
        $result2 = MatchResult::none();

        $this->repo->expectsGetMatchCriteriaForIds($ids, [
            1 => $criteria1,
            2 => $criteria2,
        ]);

        $this->matchingService->expectsFindMatch($criteria1, $result1);
        $this->matchingService->expectsFindMatch($criteria2, $result2);

        $this->repo->expectsAttachInvoice($id1, $invoiceId);
        $this->repo->expectsMarkAsResolved($id1);
        $this->repo->expectsMarkAsUnresolvable($id2);

        $this->service->resolveMatching($ids);
    }
}
