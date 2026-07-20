<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\CouldNotCompleteTransaction;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Infrastructure\BankTransactions\BankTransactionDbRepository;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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
    public function it_attaches_an_invoice(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->assertDatabaseHas('banking_transaction_references', [
            'banking_transaction_id' => $bankingTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    #[Test]
    public function it_detaches_an_invoice(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $invoice = Invoice::factory()->create();

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
    }

    #[Test]
    public function it_attaches_a_purchase_order(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->assertDatabaseHas('banking_transaction_references', [
            'banking_transaction_id' => $bankingTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);
    }

    #[Test]
    public function it_detaches_a_purchase_order(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

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

    #[Test]
    public function it_gets_attached_invoice_ids(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice1->id),
        );
        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice2->id),
        );

        $result = $this->repository->getAttachedInvoiceIds(
            BankTransactionId::create($bankingTransaction->id),
        );

        static::assertCount(2, $result->ids);
    }

    #[Test]
    public function it_gets_attached_purchase_order_ids(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create();
        $po1 = PurchaseOrder::factory()->create();
        $po2 = PurchaseOrder::factory()->create();

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($po1->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($po2->id),
        );

        $result = $this->repository->getAttachedPurchaseOrderIds(
            BankTransactionId::create($bankingTransaction->id),
        );

        static::assertCount(2, $result->ids);
    }

    #[Test]
    public function it_completes_a_banking_transaction(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create(['amount' => 100.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->repository->complete(BankTransactionId::create($bankingTransaction->id));

        $bankingTransaction->refresh();
        static::assertSame(BankTransactionStatus::Completed, $bankingTransaction->status);

        $bookkeepingRecord->refresh();
        static::assertEquals($bankingTransaction->id, $bookkeepingRecord->banking_transaction_id);
    }

    #[Test]
    public function it_throws_when_completing_with_unmatched_amount(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create(['amount' => 200.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->expectException(CouldNotCompleteTransaction::class);
        $this->repository->complete(BankTransactionId::create($bankingTransaction->id));
    }

    #[Test]
    public function it_completes_when_po_total_offsets_difference(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create(['amount' => 150.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 200.00, 'quantity' => 1]);
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $purchaseOrder->id, 'price' => 50.00]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->repository->complete(BankTransactionId::create($bankingTransaction->id));

        $bankingTransaction->refresh();
        static::assertSame(BankTransactionStatus::Completed, $bankingTransaction->status);
    }

    #[Test]
    public function it_throws_when_po_total_causes_unmatched_amount(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create(['amount' => 100.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $purchaseOrder->id, 'price' => 50.00]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->expectException(CouldNotCompleteTransaction::class);
        $this->repository->complete(BankTransactionId::create($bankingTransaction->id));
    }

    #[Test]
    public function it_completes_with_multiple_invoices_and_purchase_orders(): void
    {
        $bankingTransaction = BankingTransaction::factory()->create(['amount' => 50.00]);
        $invoice1 = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice1->id, 'price' => 100.00, 'quantity' => 1]);
        $invoice2 = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice2->id, 'price' => 50.00, 'quantity' => 1]);
        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $po1->id, 'price' => 75.00]);
        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $po2->id, 'price' => 25.00]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice1->id),
        );
        $this->repository->attachInvoice(
            BankTransactionId::create($bankingTransaction->id),
            InvoiceId::create($invoice2->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($po1->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankingTransaction->id),
            PurchaseOrderId::create($po2->id),
        );

        $this->repository->complete(BankTransactionId::create($bankingTransaction->id));

        $bankingTransaction->refresh();
        static::assertSame(BankTransactionStatus::Completed, $bankingTransaction->status);
    }
}
