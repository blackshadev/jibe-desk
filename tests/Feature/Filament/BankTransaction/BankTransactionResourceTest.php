<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BankTransaction;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankTransactions\Pages\CreateBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Admin\Resources\BankTransactions\Pages\ViewBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\RelationManagers\BookkeepingRecordsRelationManager;
use App\Filament\Admin\Resources\BankTransactions\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\BankTransactions\RelationManagers\PurchaseOrdersRelationManager;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class BankTransactionResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_list_page_is_accessible(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListBankTransactions::class)
            ->assertSuccessful();
    }

    public function test_can_list_bank_transactions(): void
    {
        $this->withAuthorizedUser();

        BankTransaction::factory()->create(['description' => 'Payment from John', 'date' => now()]);
        BankTransaction::factory()->create(['description' => 'Invoice payment', 'date' => now()]);

        Livewire::test(ListBankTransactions::class)
            ->assertCanSeeTableRecords(BankTransaction::all());
    }

    public function test_can_create_bank_transaction(): void
    {
        $this->withAuthorizedUser();

        BankAccount::factory()->create(['iban' => 'NL35RABO3010166281']);

        Livewire::test(CreateBankTransaction::class)
            ->fillForm([
                'date' => '2024-01-15',
                'description' => 'Test payment',
                'amount' => 100.50,
                'banking_account_number' => 'NL35RABO3010166281',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bank_transactions', [
            'description' => 'Test payment',
            'banking_account_number' => 'NL35RABO3010166281',
        ]);
    }

    public function test_can_create_invoice_from_transaction(): void
    {
        $this->withAuthorizedUser();

        $member = Member::factory()->createQuietly();
        $costCenter = CostCenter::factory()->create();
        $transaction = BankTransaction::factory()->create([
            'amount' => 150.000,
            'description' => 'Test payment',
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankTransaction::class,
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

        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $transaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_can_create_purchase_order_from_transaction(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->create();
        $transaction = BankTransaction::factory()->create([
            'amount' => -200.000,
            'description' => 'Office supplies',
            'banking_account_number' => 'NL91ABNA0417164300',
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(PurchaseOrdersRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankTransaction::class,
        ])
            ->callTableAction('createPurchaseOrderFromTransaction', data: [
                'cost_center_id' => $costCenter->id,
                'image_path' => UploadedFile::fake()->image('bonordnetje.jpg'),
            ])
            ->assertHasNoFormErrors();

        $po = PurchaseOrder::query()->first();
        static::assertNotNull($po);
        static::assertSame('open', $po->status->value);
        static::assertSame('NL91ABNA0417164300', $po->creditor_iban);
        static::assertNotNull($po->image_path);

        $line = PurchaseOrderLine::query()->where('purchase_order_id', $po->id)->first();
        static::assertNotNull($line);
        static::assertSame('Office supplies', $line->description);
        static::assertEqualsWithDelta(200.0, (float) $line->price, 0.001);
        static::assertEqualsWithDelta(42.0, (float) $line->price_vat, 0.001);
        static::assertSame($costCenter->id, $line->cost_center_id);

        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $transaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_can_create_bookkeeping_record_from_transaction(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->create();
        $transaction = BankTransaction::factory()->create([
            'amount' => -50.000,
            'description' => 'Office supplies',
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(BookkeepingRecordsRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankTransaction::class,
        ])
            ->callTableAction('createBookkeepingRecordFromTransaction', data: [
                'cost_center_id' => $costCenter->id,
            ])
            ->assertHasNoFormErrors();

        $record = BookkeepingRecord::query()->first();
        static::assertNotNull($record);
        static::assertSame(now()->year, $record->year);
        static::assertSame('Office supplies', $record->description);
        static::assertSame($transaction->id, $record->bank_transaction_id);
    }

    public function test_bookkeeping_record_amount_uses_abs_for_negative_transaction(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->create();
        $transaction = BankTransaction::factory()->create([
            'amount' => -100.000,
            'status' => BankTransactionStatus::Open,
        ]);

        Livewire::test(BookkeepingRecordsRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewBankTransaction::class,
        ])
            ->callTableAction('createBookkeepingRecordFromTransaction', data: [
                'cost_center_id' => $costCenter->id,
            ])
            ->assertHasNoFormErrors();

        $record = BookkeepingRecord::query()->first();
        static::assertNotNull($record);
        static::assertEqualsWithDelta(-100.0, (float) $record->amount_price, 0.001);
    }

    public function test_bank_transactions_are_grouped_by_month_with_amount_summary(): void
    {
        $this->withAuthorizedUser();

        BankTransaction::factory()->create(['date' => '2026-01-10', 'amount' => 100.00]);
        BankTransaction::factory()->create(['date' => '2026-01-20', 'amount' => -30.00]);
        BankTransaction::factory()->create(['date' => '2026-02-05', 'amount' => 50.00]);

        Livewire::test(ListBankTransactions::class)
            ->assertSuccessful()
            ->assertSee('2026-01')
            ->assertSee('2026-02')
            ->assertTableColumnSummarySet('amount', 'total_amount', 120.00)
            ->assertTableColumnSummarySet('amount', 'running_total', '');
    }
}
