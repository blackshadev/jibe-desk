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
            description: 'Monthly fee',
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
            description: 'Monthly fee',
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
            description: 'Monthly fee',
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
            description: 'Monthly fee',
        );

        $criteria2 = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-16'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164301',
            description: 'Monthly fee',
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

    public function test_resolve_matching_with_reversal_calls_link_reversal_and_mark_resolved(): void
    {
        $bankTransactionId = BankTransactionId::create(1);
        $originalId = BankTransactionId::create(2);
        $ids = new BankTransactionIdList([$bankTransactionId]);

        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $result = MatchResult::foundReversal($originalId);
        $emptyInvoiceIds = InvoiceIdList::fromArray([]);
        $emptyPurchaseOrderIds = PurchaseOrderIdList::fromArray([]);

        $this->repo->expectsGetMatchCriteriaForIds($ids, [1 => $criteria]);
        $this->matchingService->expectsFindMatch($criteria, $result);
        $this->repo->expectsLinkReversal($originalId, $bankTransactionId);
        $this->repo->expectsGetAttachedInvoiceIds($bankTransactionId, $emptyInvoiceIds);
        $this->invoiceService->expectsMarkAsDeclined($emptyInvoiceIds);
        $this->repo->expectsGetAttachedPurchaseOrderIds($bankTransactionId, $emptyPurchaseOrderIds);
        $this->purchaseOrderService->expectsMarkAsDeclined($emptyPurchaseOrderIds);
        $this->repo->expectsMarkAsResolved($originalId);
        $this->repo->expectsMarkAsResolved($bankTransactionId);
        $this->repo->expectsMarkAsResolved($bankTransactionId);

        $this->service->resolveMatching($ids);
    }

    public function test_link_reversal_marks_attached_invoices_as_declined(): void
    {
        $reversalId = BankTransactionId::create(1);
        $originalId = BankTransactionId::create(2);
        $invoiceIds = InvoiceIdList::fromArray([10, 20]);
        $emptyPurchaseOrderIds = PurchaseOrderIdList::fromArray([]);

        $this->repo->expectsLinkReversal($reversalId, $originalId);
        $this->repo->expectsGetAttachedInvoiceIds($originalId, $invoiceIds);
        $this->invoiceService->expectsMarkAsDeclined($invoiceIds);
        $this->repo->expectsGetAttachedPurchaseOrderIds($originalId, $emptyPurchaseOrderIds);
        $this->purchaseOrderService->expectsMarkAsDeclined($emptyPurchaseOrderIds);
        $this->repo->expectsMarkAsResolved($reversalId);
        $this->repo->expectsMarkAsResolved($originalId);

        $this->service->linkReversal($reversalId, $originalId);
    }

    public function test_link_reversal_marks_attached_purchase_orders_as_declined(): void
    {
        $reversalId = BankTransactionId::create(1);
        $originalId = BankTransactionId::create(2);
        $emptyInvoiceIds = InvoiceIdList::fromArray([]);
        $purchaseOrderIds = PurchaseOrderIdList::fromArray([30, 40]);

        $this->repo->expectsLinkReversal($reversalId, $originalId);
        $this->repo->expectsGetAttachedInvoiceIds($originalId, $emptyInvoiceIds);
        $this->invoiceService->expectsMarkAsDeclined($emptyInvoiceIds);
        $this->repo->expectsGetAttachedPurchaseOrderIds($originalId, $purchaseOrderIds);
        $this->purchaseOrderService->expectsMarkAsDeclined($purchaseOrderIds);
        $this->repo->expectsMarkAsResolved($reversalId);
        $this->repo->expectsMarkAsResolved($originalId);

        $this->service->linkReversal($reversalId, $originalId);
    }

    public function test_link_reversal_does_not_call_decline_when_no_attached_references(): void
    {
        $reversalId = BankTransactionId::create(1);
        $originalId = BankTransactionId::create(2);
        $emptyInvoiceIds = InvoiceIdList::fromArray([]);
        $emptyPurchaseOrderIds = PurchaseOrderIdList::fromArray([]);

        $this->repo->expectsLinkReversal($reversalId, $originalId);
        $this->repo->expectsGetAttachedInvoiceIds($originalId, $emptyInvoiceIds);
        $this->invoiceService->expectsMarkAsDeclined($emptyInvoiceIds);
        $this->repo->expectsGetAttachedPurchaseOrderIds($originalId, $emptyPurchaseOrderIds);
        $this->purchaseOrderService->expectsMarkAsDeclined($emptyPurchaseOrderIds);
        $this->repo->expectsMarkAsResolved($reversalId);
        $this->repo->expectsMarkAsResolved($originalId);

        $this->service->linkReversal($reversalId, $originalId);
    }

    public function test_unlink_reversal_marks_attached_references_as_pending(): void
    {
        $reversalId = BankTransactionId::create(1);
        $invoiceIds = InvoiceIdList::fromArray([10, 20]);
        $purchaseOrderIds = PurchaseOrderIdList::fromArray([30, 40]);

        $this->repo->expectsGetAttachedInvoiceIds($reversalId, $invoiceIds);
        $this->invoiceService->expectsMarkAsPending($invoiceIds);
        $this->repo->expectsGetAttachedPurchaseOrderIds($reversalId, $purchaseOrderIds);
        $this->purchaseOrderService->expectsMarkAsPending($purchaseOrderIds);
        $this->repo->expectsUnlinkReversal($reversalId);

        $this->service->unlinkReversal($reversalId);
    }
}
