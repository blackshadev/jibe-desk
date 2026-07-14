<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Infrastructure\BankTransactions\BankTransactionDbRepository;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

final class BankTransactionDbRepositoryTest extends FeatureTestCase
{
    private BankTransactionDbRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new BankTransactionDbRepository();
    }

    #[Test]
    public function it_creates_a_banking_transaction_and_returns_id(): void
    {
        $dto = new CreateBankTransaction(
            date: '2024-01-15',
            amount: 100.50,
            description: 'Test payment',
            bankingAccountNumber: 'NL91ABNA0417164300',
            importHash: 'abc123',
        );

        $id = $this->repository->create($dto);

        $this->assertInstanceOf(BankTransactionId::class, $id);
        $this->assertDatabaseHas('banking_transactions', [
            'description' => 'Test payment',
            'banking_account_number' => 'NL91ABNA0417164300',
            'import_hash' => 'abc123',
        ]);
    }

    #[Test]
    public function it_checks_if_hash_exists(): void
    {
        BankingTransaction::factory()->create(['import_hash' => 'existing_hash']);

        $this->assertTrue($this->repository->existsByHash('existing_hash'));
        $this->assertFalse($this->repository->existsByHash('nonexistent_hash'));
    }

    #[Test]
    public function it_attaches_an_invoice_and_links_bookkeeping_records(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $invoice = Invoice::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->assertDatabaseHas('banking_transaction_references', [
            'banking_transaction_id' => $bankingTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        $bookkeepingRecord->refresh();
        $this->assertEquals($bankingTransaction->id, $bookkeepingRecord->banking_transaction_id);
    }

    #[Test]
    public function it_detaches_an_invoice_and_clears_bookkeeping_records(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $invoice = Invoice::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'banking_transaction_id' => $bankingTransaction->id,
        ]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->repository->detachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->assertDatabaseMissing('banking_transaction_references', [
            'banking_transaction_id' => $bankingTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        $bookkeepingRecord->refresh();
        $this->assertNull($bookkeepingRecord->banking_transaction_id);
    }

    #[Test]
    public function it_attaches_a_purchase_order_and_links_bookkeeping_records(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->assertDatabaseHas('banking_transaction_references', [
            'banking_transaction_id' => $bankingTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);

        $bookkeepingRecord->refresh();
        $this->assertEquals($bankingTransaction->id, $bookkeepingRecord->banking_transaction_id);
    }

    #[Test]
    public function it_detaches_a_purchase_order_and_clears_bookkeeping_records(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
            'banking_transaction_id' => $bankingTransaction->id,
        ]);

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->repository->detachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->assertDatabaseMissing('banking_transaction_references', [
            'banking_transaction_id' => $bankingTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);

        $bookkeepingRecord->refresh();
        $this->assertNull($bookkeepingRecord->banking_transaction_id);
    }

    #[Test]
    public function it_attaches_a_bookkeeping_record_directly(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create();

        $this->repository->attachBookkeepingRecord(
            BankTransactionId::create($bankingTransaction->id),
            $bookkeepingRecord->id,
        );

        $bookkeepingRecord->refresh();
        $this->assertEquals($bankingTransaction->id, $bookkeepingRecord->banking_transaction_id);
    }

    #[Test]
    public function it_detaches_a_bookkeeping_record(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'banking_transaction_id' => $bankingTransaction->id,
        ]);

        $this->repository->detachBookkeepingRecord(
            BankTransactionId::create($bankingTransaction->id),
            $bookkeepingRecord->id,
        );

        $bookkeepingRecord->refresh();
        $this->assertNull($bookkeepingRecord->banking_transaction_id);
    }
}
