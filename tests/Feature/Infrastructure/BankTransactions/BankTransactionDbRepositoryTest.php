<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\CouldNotCompleteTransaction;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\BankTransactions\ResolveStatus;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Infrastructure\BankTransactions\BankTransactionDbRepository;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Override;
use Tests\FeatureTestCase;

final class BankTransactionDbRepositoryTest extends FeatureTestCase
{
    private BankTransactionDbRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new BankTransactionDbRepository();
    }

    public function test_it_creates_a_banking_transaction_and_returns_id(): void
    {
        $bankAccount = BankAccount::factory()->create();

        $dto = new CreateBankTransaction(
            date: '2024-01-15',
            amount: 100.50,
            description: 'Test payment',
            bankingAccountNumber: 'NL91ABNA0417164300',
            bankAccountId: $bankAccount->id,
            bankStatementId: null,
            importHash: 'abc123',
        );

        $id = $this->repository->create($dto);

        static::assertInstanceOf(BankTransactionId::class, $id);
        $this->assertDatabaseHas('bank_transactions', [
            'description' => 'Test payment',
            'banking_account_number' => 'NL91ABNA0417164300',
            'import_hash' => 'abc123',
        ]);
    }

    public function test_it_checks_if_hash_exists(): void
    {
        BankTransaction::factory()->create(['import_hash' => 'existing_hash']);

        static::assertTrue($this->repository->existsByHash('existing_hash'));
        static::assertFalse($this->repository->existsByHash('nonexistent_hash'));
    }

    public function test_it_attaches_an_invoice(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $bankTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_it_detaches_an_invoice(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->repository->detachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->assertDatabaseMissing('bank_transaction_references', [
            'bank_transaction_id' => $bankTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_it_attaches_a_purchase_order(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $bankTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);
    }

    public function test_it_detaches_a_purchase_order(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->repository->detachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->assertDatabaseMissing('bank_transaction_references', [
            'bank_transaction_id' => $bankTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);
    }

    public function test_it_attaches_a_bookkeeping_record_directly(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create();

        $this->repository->attachBookkeepingRecord(
            BankTransactionId::create($bankTransaction->id),
            $bookkeepingRecord->id,
        );

        $bookkeepingRecord->refresh();
        static::assertEquals($bankTransaction->id, $bookkeepingRecord->bank_transaction_id);
    }

    public function test_it_detaches_a_bookkeeping_record(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'bank_transaction_id' => $bankTransaction->id,
        ]);

        $this->repository->detachBookkeepingRecord(
            BankTransactionId::create($bankTransaction->id),
            $bookkeepingRecord->id,
        );

        $bookkeepingRecord->refresh();
        static::assertNull($bookkeepingRecord->bank_transaction_id);
    }

    public function test_it_gets_attached_invoice_ids(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice1->id),
        );
        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice2->id),
        );

        $result = $this->repository->getAttachedInvoiceIds(
            BankTransactionId::create($bankTransaction->id),
        );

        static::assertCount(2, $result->ids);
    }

    public function test_it_gets_attached_purchase_order_ids(): void
    {
        $bankTransaction = BankTransaction::factory()->create();
        $po1 = PurchaseOrder::factory()->create();
        $po2 = PurchaseOrder::factory()->create();

        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($po1->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($po2->id),
        );

        $result = $this->repository->getAttachedPurchaseOrderIds(
            BankTransactionId::create($bankTransaction->id),
        );

        static::assertCount(2, $result->ids);
    }

    public function test_it_completes_a_banking_transaction(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['amount' => 100.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);
        $bookkeepingRecord = BookkeepingRecord::factory()->create([
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->repository->complete(BankTransactionId::create($bankTransaction->id));

        $bankTransaction->refresh();
        static::assertSame(BankTransactionStatus::Completed, $bankTransaction->status);

        $bookkeepingRecord->refresh();
        static::assertEquals($bankTransaction->id, $bookkeepingRecord->bank_transaction_id);
    }

    public function test_it_throws_when_completing_with_unmatched_amount(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['amount' => 200.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );

        $this->expectException(CouldNotCompleteTransaction::class);
        $this->repository->complete(BankTransactionId::create($bankTransaction->id));
    }

    public function test_it_completes_when_po_total_offsets_difference(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['amount' => 150.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 200.00, 'quantity' => 1]);
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $purchaseOrder->id, 'price' => 50.00]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->repository->complete(BankTransactionId::create($bankTransaction->id));

        $bankTransaction->refresh();
        static::assertSame(BankTransactionStatus::Completed, $bankTransaction->status);
    }

    public function test_it_throws_when_po_total_causes_unmatched_amount(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['amount' => 100.00]);
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $purchaseOrder->id, 'price' => 50.00]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($purchaseOrder->id),
        );

        $this->expectException(CouldNotCompleteTransaction::class);
        $this->repository->complete(BankTransactionId::create($bankTransaction->id));
    }

    public function test_it_completes_with_multiple_invoices_and_purchase_orders(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['amount' => 50.00]);
        $invoice1 = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice1->id, 'price' => 100.00, 'quantity' => 1]);
        $invoice2 = Invoice::factory()->create();
        InvoiceLine::factory()->create(['invoice_id' => $invoice2->id, 'price' => 50.00, 'quantity' => 1]);
        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $po1->id, 'price' => 75.00]);
        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $po2->id, 'price' => 25.00]);

        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice1->id),
        );
        $this->repository->attachInvoice(
            BankTransactionId::create($bankTransaction->id),
            InvoiceId::create($invoice2->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($po1->id),
        );
        $this->repository->attachPurchaseOrder(
            BankTransactionId::create($bankTransaction->id),
            PurchaseOrderId::create($po2->id),
        );

        $this->repository->complete(BankTransactionId::create($bankTransaction->id));

        $bankTransaction->refresh();
        static::assertSame(BankTransactionStatus::Completed, $bankTransaction->status);
    }

    public function test_get_unresolved_ids_returns_only_unresolved(): void
    {
        $unresolved1 = BankTransaction::factory()->create(['resolve_status' => ResolveStatus::Unresolved->value]);
        $unresolved2 = BankTransaction::factory()->create(['resolve_status' => ResolveStatus::Unresolved->value]);
        BankTransaction::factory()->create(['resolve_status' => ResolveStatus::Resolved->value]);
        BankTransaction::factory()->create(['resolve_status' => ResolveStatus::Unresolvable->value]);

        $result = $this->repository->getUnresolvedIds(50);

        static::assertCount(2, $result->ids);

        $resultIds = array_map(static fn ($id) => $id->value, $result->ids);
        static::assertContains($unresolved1->id, $resultIds);
        static::assertContains($unresolved2->id, $resultIds);
    }

    public function test_get_unresolved_unresolved_ids_respects_limit(): void
    {
        BankTransaction::factory()->count(10)->create(['resolve_status' => ResolveStatus::Unresolved->value]);

        $result = $this->repository->getUnresolvedIds(5);

        static::assertCount(5, $result->ids);
    }

    public function test_mark_as_resolved(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['resolve_status' => ResolveStatus::Unresolved->value]);

        $this->repository->markAsResolved(BankTransactionId::create($bankTransaction->id));

        $bankTransaction->refresh();
        static::assertSame(ResolveStatus::Resolved, $bankTransaction->resolve_status);
    }

    public function test_mark_as_unresolvable(): void
    {
        $bankTransaction = BankTransaction::factory()->create(['resolve_status' => ResolveStatus::Unresolved->value]);

        $this->repository->markAsUnresolvable(BankTransactionId::create($bankTransaction->id));

        $bankTransaction->refresh();
        static::assertSame(ResolveStatus::Unresolvable, $bankTransaction->resolve_status);
    }

    public function test_get_match_criteria_for_ids(): void
    {
        $bt1 = BankTransaction::factory()->create([
            'date' => '2026-01-15',
            'amount' => 100.50,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);
        $bt2 = BankTransaction::factory()->create([
            'date' => '2026-01-16',
            'amount' => -50.25,
            'banking_account_number' => 'NL91ABNA0417164301',
        ]);

        $ids = new BankTransactionIdList([
            BankTransactionId::create($bt1->id),
            BankTransactionId::create($bt2->id),
        ]);

        $result = $this->repository->getMatchCriteriaForIds($ids);

        static::assertCount(2, $result);
        static::assertArrayHasKey($bt1->id, $result);
        static::assertArrayHasKey($bt2->id, $result);
        static::assertSame(100.50, $result[$bt1->id]->amount);
        static::assertSame('NL91ABNA0417164300', $result[$bt1->id]->bankingAccountNumber);
        static::assertEquals(-50.25, $result[$bt2->id]->amount);
    }

    public function test_get_match_criteria_returns_empty_for_nonexistent_ids(): void
    {
        $ids = new BankTransactionIdList([BankTransactionId::create(99_999)]);

        $result = $this->repository->getMatchCriteriaForIds($ids);

        static::assertEmpty($result);
    }
}
