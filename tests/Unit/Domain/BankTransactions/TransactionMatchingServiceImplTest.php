<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\MatchCriteria;
use App\Domain\BankTransactions\TransactionMatchingServiceImpl;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use DateTimeImmutable;
use Override;
use Tests\Unit\Domain\Invoices\InvoiceMatchingRepositoryExpectation;
use Tests\Unit\Domain\PurchaseOrders\PurchaseOrderRepositoryExpectation;
use Tests\UnitTestCase;

final class TransactionMatchingServiceImplTest extends UnitTestCase
{
    private InvoiceMatchingRepositoryExpectation $invoiceRepository;
    private PurchaseOrderRepositoryExpectation $purchaseOrderRepository;
    private BankTransactionRepositoryExpectation $bankTransactionRepository;
    private TransactionMatchingServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceRepository = InvoiceMatchingRepositoryExpectation::create();
        $this->purchaseOrderRepository = PurchaseOrderRepositoryExpectation::create();
        $this->bankTransactionRepository = BankTransactionRepositoryExpectation::create();

        $this->service = new TransactionMatchingServiceImpl(
            $this->invoiceRepository->mock,
            $this->purchaseOrderRepository->mock,
            $this->bankTransactionRepository->mock,
        );
    }

    public function test_find_match_positive_amount_returns_match_result_with_invoice(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $invoiceId = InvoiceId::create(42);

        $this->invoiceRepository->expectsFindMatchingCredit('NL91ABNA0417164300', 100.00, $criteria->date, $invoiceId);

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->invoiceId);
        static::assertSame(42, $result->invoiceId->value);
        static::assertNull($result->purchaseOrderId);
    }

    public function test_find_match_positive_amount_no_match_returns_none(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $this->invoiceRepository->expectsFindMatchingCredit('NL91ABNA0417164300', 100.00, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, null);

        $result = $this->service->findMatch($criteria);

        static::assertFalse($result->isMatch);
        static::assertNull($result->invoiceId);
        static::assertNull($result->purchaseOrderId);
    }

    public function test_find_match_negative_amount_returns_match_result_with_purchase_order(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $purchaseOrderId = PurchaseOrderId::create(7);

        $this->purchaseOrderRepository->expectsFindMatchingDebit('NL91ABNA0417164300', 50.00, $criteria->date, $purchaseOrderId);

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNull($result->invoiceId);
        static::assertNotNull($result->purchaseOrderId);
        static::assertSame(7, $result->purchaseOrderId->value);
    }

    public function test_find_match_negative_amount_no_match_returns_none(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $this->purchaseOrderRepository->expectsFindMatchingDebit('NL91ABNA0417164300', 50.00, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, null);

        $result = $this->service->findMatch($criteria);

        static::assertFalse($result->isMatch);
        static::assertNull($result->invoiceId);
        static::assertNull($result->purchaseOrderId);
    }

    public function test_find_match_zero_amount_tries_purchase_order(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 0.0,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $this->purchaseOrderRepository->expectsFindMatchingDebit('NL91ABNA0417164300', 0.0, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, null);

        $result = $this->service->findMatch($criteria);

        static::assertFalse($result->isMatch);
    }

    public function test_find_match_falls_back_to_reversal_when_no_invoice_match(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $reversalId = BankTransactionId::create(42);

        $this->invoiceRepository->expectsFindMatchingCredit('NL91ABNA0417164300', 100.00, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, $reversalId);

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->reversedByTransactionId);
        static::assertSame(42, $result->reversedByTransactionId->value);
    }

    public function test_find_match_falls_back_to_reversal_when_no_purchase_order_match(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $reversalId = BankTransactionId::create(7);

        $this->purchaseOrderRepository->expectsFindMatchingDebit('NL91ABNA0417164300', 50.00, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, $reversalId);

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->reversedByTransactionId);
        static::assertSame(7, $result->reversedByTransactionId->value);
    }

    public function test_find_match_returns_none_when_no_invoice_and_no_reversal(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $this->invoiceRepository->expectsFindMatchingCredit('NL91ABNA0417164300', 100.00, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, null);

        $result = $this->service->findMatch($criteria);

        static::assertFalse($result->isMatch);
        static::assertNull($result->invoiceId);
        static::assertNull($result->purchaseOrderId);
        static::assertNull($result->reversedByTransactionId);
    }
}
