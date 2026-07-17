<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BankingTransaction;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\BankingTransactions\Pages\CreateBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ListBankingTransactions;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class BankingTransactionResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_list_page_is_accessible(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListBankingTransactions::class)
            ->assertSuccessful();
    }

    public function test_can_list_banking_transactions(): void
    {
        $this->withAuthorizedUser();

        BankingTransaction::factory()->create(['description' => 'Payment from John']);
        BankingTransaction::factory()->create(['description' => 'Invoice payment']);

        Livewire::test(ListBankingTransactions::class)
            ->assertCanSeeTableRecords(BankingTransaction::all());
    }

    public function test_can_create_banking_transaction(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateBankingTransaction::class)
            ->fillForm([
                'date' => '2024-01-15',
                'description' => 'Test payment',
                'amount' => 100.50,
                'banking_account_number' => 'NL91ABNA0417164300',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('banking_transactions', [
            'description' => 'Test payment',
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);
    }

    public function test_open_or_pending_scope_filters_correct_statuses_on_invoice(): void
    {
        $this->withAuthorizedUser();

        $member = Member::factory()->createQuietly();
        Invoice::factory()->forMember($member)->createQuietly(['status' => InvoiceStatus::Open]);
        Invoice::factory()->forMember($member)->createQuietly(['status' => InvoiceStatus::Pending]);
        Invoice::factory()->forMember($member)->createQuietly(['status' => InvoiceStatus::Paid]);
        Invoice::factory()->forMember($member)->createQuietly(['status' => InvoiceStatus::Declined]);

        $results = Invoice::query()->openOrPending()->get();

        static::assertCount(2, $results);
        static::assertTrue($results->contains('status', InvoiceStatus::Open));
        static::assertTrue($results->contains('status', InvoiceStatus::Pending));
        static::assertFalse($results->contains('status', InvoiceStatus::Paid));
        static::assertFalse($results->contains('status', InvoiceStatus::Declined));
    }

    public function test_open_or_pending_scope_filters_correct_statuses_on_purchase_order(): void
    {
        $this->withAuthorizedUser();

        PurchaseOrder::factory()->open()->createQuietly();
        PurchaseOrder::factory()->pending()->createQuietly();
        PurchaseOrder::factory()->paid()->createQuietly();

        $results = PurchaseOrder::query()->openOrPending()->get();

        static::assertCount(2, $results);
        static::assertTrue($results->contains('status', PurchaseOrderStatus::Open));
        static::assertTrue($results->contains('status', PurchaseOrderStatus::Pending));
        static::assertFalse($results->contains('status', PurchaseOrderStatus::Paid));
    }

    public function test_attaching_invoice_marks_it_as_paid(): void
    {
        $this->withAuthorizedUser();

        $bt = BankingTransaction::factory()->createQuietly(['amount' => 100.00]);
        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()->forMember($member)->createQuietly(['status' => InvoiceStatus::Open]);
        InvoiceLine::factory()->createQuietly(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);

        app(BankTransactionService::class)->attachInvoice(
            BankTransactionId::create($bt->id),
            InvoiceId::create($invoice->id),
        );

        $invoice->refresh();
        static::assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_attaching_invoice_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();

        $bt = BankingTransaction::factory()->createQuietly(['amount' => 100.00]);
        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()->forMember($member)->createQuietly(['status' => InvoiceStatus::Open]);
        InvoiceLine::factory()->createQuietly(['invoice_id' => $invoice->id, 'price' => 100.00, 'quantity' => 1]);

        app(BankTransactionService::class)->attachInvoice(
            BankTransactionId::create($bt->id),
            InvoiceId::create($invoice->id),
        );

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_attaching_purchase_order_marks_it_as_paid(): void
    {
        $this->withAuthorizedUser();

        $bt = BankingTransaction::factory()->createQuietly(['amount' => 100.00]);
        $po = PurchaseOrder::factory()->open()->createQuietly(['creditor_iban' => $bt->banking_account_number]);
        PurchaseOrderLine::factory()->createQuietly(['purchase_order_id' => $po->id, 'price' => 100.00]);

        app(BankTransactionService::class)->attachPurchaseOrder(
            BankTransactionId::create($bt->id),
            PurchaseOrderId::create($po->id),
        );

        $po->refresh();
        static::assertSame(PurchaseOrderStatus::Paid, $po->status);
    }

    public function test_attaching_purchase_order_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();

        $bt = BankingTransaction::factory()->createQuietly(['amount' => 100.00]);
        $po = PurchaseOrder::factory()->open()->createQuietly(['creditor_iban' => $bt->banking_account_number]);
        PurchaseOrderLine::factory()->createQuietly(['purchase_order_id' => $po->id, 'price' => 100.00]);

        app(BankTransactionService::class)->attachPurchaseOrder(
            BankTransactionId::create($bt->id),
            PurchaseOrderId::create($po->id),
        );

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }
}
