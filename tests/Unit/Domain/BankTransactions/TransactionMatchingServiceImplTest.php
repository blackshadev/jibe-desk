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
use Tests\Unit\Domain\BankAccounts\BankAccountRepositoryExpectation;
use Tests\Unit\Domain\Invoices\InvoiceMatchingRepositoryExpectation;
use Tests\Unit\Domain\PurchaseOrders\PurchaseOrderRepositoryExpectation;
use Tests\UnitTestCase;

final class TransactionMatchingServiceImplTest extends UnitTestCase
{
    private InvoiceMatchingRepositoryExpectation $invoiceRepository;
    private PurchaseOrderRepositoryExpectation $purchaseOrderRepository;
    private BankTransactionRepositoryExpectation $bankTransactionRepository;
    private BankAccountRepositoryExpectation $bankAccountRepository;
    private TransactionMatchingServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceRepository = InvoiceMatchingRepositoryExpectation::create();
        $this->purchaseOrderRepository = PurchaseOrderRepositoryExpectation::create();
        $this->bankTransactionRepository = BankTransactionRepositoryExpectation::create();
        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();

        $this->bankAccountRepository->defaultsGetOwnAccounts([]);

        $this->service = new TransactionMatchingServiceImpl(
            $this->invoiceRepository->mock,
            $this->purchaseOrderRepository->mock,
            $this->bankTransactionRepository->mock,
            $this->bankAccountRepository->mock,
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

    public function test_find_match_zero_amount_falls_back_to_reversal_when_no_purchase_order_match(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 0.0,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $reversalId = BankTransactionId::create(3);

        $this->purchaseOrderRepository->expectsFindMatchingDebit('NL91ABNA0417164300', 0.0, $criteria->date, null);
        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, $reversalId);

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->reversedByTransactionId);
        static::assertSame(3, $result->reversedByTransactionId->value);
    }

    public function test_find_match_internal_transfer_positive_amount_returns_counterpart(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 250.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Transfer between own accounts',
        );

        $counterpartId = BankTransactionId::create(9);

        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();
        $this->bankAccountRepository->expectsGetOwnAccounts(['NL91ABNA0417164300']);
        $this->bankTransactionRepository->expectsFindInternalTransferMatch($criteria, $counterpartId);

        $this->service = new TransactionMatchingServiceImpl(
            $this->invoiceRepository->mock,
            $this->purchaseOrderRepository->mock,
            $this->bankTransactionRepository->mock,
            $this->bankAccountRepository->mock,
        );

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->internalTransferCounterpartId);
        static::assertSame(9, $result->internalTransferCounterpartId->value);
        static::assertNull($result->invoiceId);
        static::assertNull($result->purchaseOrderId);
        static::assertNull($result->reversedByTransactionId);
    }

    public function test_find_match_internal_transfer_negative_amount_returns_counterpart(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: -250.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Transfer between own accounts',
        );

        $counterpartId = BankTransactionId::create(11);

        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();
        $this->bankAccountRepository->expectsGetOwnAccounts(['NL91ABNA0417164300']);
        $this->bankTransactionRepository->expectsFindInternalTransferMatch($criteria, $counterpartId);

        $this->service = new TransactionMatchingServiceImpl(
            $this->invoiceRepository->mock,
            $this->purchaseOrderRepository->mock,
            $this->bankTransactionRepository->mock,
            $this->bankAccountRepository->mock,
        );

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->internalTransferCounterpartId);
        static::assertSame(11, $result->internalTransferCounterpartId->value);
    }

    public function test_find_match_internal_transfer_without_counterpart_returns_match_with_null_counterpart(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 75.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Transfer between own accounts',
        );

        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();
        $this->bankAccountRepository->expectsGetOwnAccounts(['NL91ABNA0417164300']);
        $this->bankTransactionRepository->expectsFindInternalTransferMatch($criteria, null);

        $this->service = new TransactionMatchingServiceImpl(
            $this->invoiceRepository->mock,
            $this->purchaseOrderRepository->mock,
            $this->bankTransactionRepository->mock,
            $this->bankAccountRepository->mock,
        );

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNull($result->internalTransferCounterpartId);
    }

    public function test_find_match_internal_transfer_normalizes_counterparty_iban(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 120.00,
            bankingAccountNumber: 'nl91 abna 0417 1643 00',
            description: 'Transfer between own accounts',
        );

        $counterpartId = BankTransactionId::create(13);

        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();
        $this->bankAccountRepository->expectsGetOwnAccounts(['NL91ABNA0417164300']);
        $this->bankTransactionRepository->expectsFindInternalTransferMatch($criteria, $counterpartId);

        $this->service = new TransactionMatchingServiceImpl(
            $this->invoiceRepository->mock,
            $this->purchaseOrderRepository->mock,
            $this->bankTransactionRepository->mock,
            $this->bankAccountRepository->mock,
        );

        $result = $this->service->findMatch($criteria);

        static::assertTrue($result->isMatch);
        static::assertNotNull($result->internalTransferCounterpartId);
        static::assertSame(13, $result->internalTransferCounterpartId->value);
    }

    public function test_find_reversal_match_returns_bank_transaction_id(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $reversalId = BankTransactionId::create(21);

        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, $reversalId);

        $result = $this->service->findReversalMatch($criteria);

        static::assertNotNull($result);
        static::assertSame(21, $result->value);
    }

    public function test_find_reversal_match_returns_null_when_no_match(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: 100.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
            description: 'Monthly fee',
        );

        $this->bankTransactionRepository->expectsFindReversalMatch($criteria, null);

        $result = $this->service->findReversalMatch($criteria);

        static::assertNull($result);
    }
}
