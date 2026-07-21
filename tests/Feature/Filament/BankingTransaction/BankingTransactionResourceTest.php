<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BankingTransaction;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankingTransactions\Pages\CreateBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ListBankingTransactions;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\RelationManagers\BookkeepingRecordsRelationManager;
use App\Filament\Admin\Resources\BankingTransactions\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\BankingTransactions\RelationManagers\PurchaseOrdersRelationManager;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
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

    public function test_can_create_invoice_from_transaction(): void
    {
        $this->withAuthorizedUser();

        $member = Member::factory()->createQuietly();
        $costCenter = CostCenter::factory()->create();
        $transaction = BankingTransaction::factory()->create([
            'amount' => 150.000,
            'description' => 'Test payment',
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankingTransaction::class,
        ])
            ->callTableAction('createInvoiceFromTransaction', data: [
                'member_id' => $member->id,
                'cost_center_id' => $costCenter->id,
            ])
            ->assertHasNoFormErrors();

        $invoice = Invoice::query()->where('member_id', $member->id)->first();
        static::assertNotNull($invoice);
        static::assertSame('open', $invoice->status->value);

        $line = InvoiceLine::query()->where('invoice_id', $invoice->id)->first();
        static::assertNotNull($line);
        static::assertSame('Test payment', $line->description);
        static::assertEqualsWithDelta(150.0, (float) $line->price, 0.001);
        static::assertEqualsWithDelta(31.5, (float) $line->vat, 0.001);
        static::assertEqualsWithDelta(1, (float) $line->quantity, 0.001);
        static::assertSame($costCenter->id, $line->cost_center_id);

        $this->assertDatabaseHas('banking_transaction_references', [
            'banking_transaction_id' => $transaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_can_create_purchase_order_from_transaction(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->create();
        $transaction = BankingTransaction::factory()->create([
            'amount' => -200.000,
            'description' => 'Office supplies',
            'banking_account_number' => 'NL91ABNA0417164300',
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(PurchaseOrdersRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankingTransaction::class,
        ])
            ->callTableAction('createPurchaseOrderFromTransaction', data: [
                'cost_center_id' => $costCenter->id,
            ])
            ->assertHasNoFormErrors();

        $po = PurchaseOrder::query()->first();
        static::assertNotNull($po);
        static::assertSame('open', $po->status->value);
        static::assertSame('NL91ABNA0417164300', $po->creditor_iban);

        $line = PurchaseOrderLine::query()->where('purchase_order_id', $po->id)->first();
        static::assertNotNull($line);
        static::assertSame('Office supplies', $line->description);
        static::assertEqualsWithDelta(200.0, (float) $line->price, 0.001);
        static::assertEqualsWithDelta(42.0, (float) $line->price_vat, 0.001);
        static::assertSame($costCenter->id, $line->cost_center_id);

        $this->assertDatabaseHas('banking_transaction_references', [
            'banking_transaction_id' => $transaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_can_create_bookkeeping_record_from_transaction(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->create();
        $transaction = BankingTransaction::factory()->create([
            'amount' => -50.000,
            'description' => 'Office supplies',
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(BookkeepingRecordsRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankingTransaction::class,
        ])
            ->callTableAction('createBookkeepingRecordFromTransaction', data: [
                'cost_center_id' => $costCenter->id,
            ])
            ->assertHasNoFormErrors();

        $record = BookkeepingRecord::query()->first();
        static::assertNotNull($record);
        static::assertSame(now()->year, $record->year);
        static::assertSame('Office supplies', $record->description);
        static::assertSame($transaction->id, $record->banking_transaction_id);
    }

    public function test_bookkeeping_record_amount_uses_abs_for_negative_transaction(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->create();
        $transaction = BankingTransaction::factory()->create([
            'amount' => -100.000,
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(BookkeepingRecordsRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankingTransaction::class,
        ])
            ->callTableAction('createBookkeepingRecordFromTransaction', data: [
                'cost_center_id' => $costCenter->id,
            ])
            ->assertHasNoFormErrors();

        $record = BookkeepingRecord::query()->first();
        static::assertNotNull($record);
        static::assertEqualsWithDelta(-100.0, (float) $record->amount_price, 0.001);
    }
}
