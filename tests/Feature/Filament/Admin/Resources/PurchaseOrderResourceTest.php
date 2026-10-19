<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderHasNoCompletedTransactionsException;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderService;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBankTransactionsRelationManager;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBookkeepingRecordsRelationManager;
use App\Models\BankTransaction;
use App\Models\CostCenter;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class PurchaseOrderResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_financial_administration_can_list_purchase_orders(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListPurchaseOrders::class)
            ->assertSuccessful();
    }

    public function test_purchase_orders_table_shows_the_approval_status(): void
    {
        $this->withAuthorizedUser();
        $pending = PurchaseOrder::factory()->open()->createQuietly(['date' => now()]);
        $accepted = PurchaseOrder::factory()->accepted()->createQuietly(['date' => now()]);
        $declined = PurchaseOrder::factory()->cancelled()->createQuietly(['date' => now()]);

        Livewire::test(ListPurchaseOrders::class)
            ->assertCanSeeTableRecords([$pending, $accepted, $declined])
            ->assertTableColumnFormattedStateSet('approval_status', 'Wacht op goedkeuring', $pending)
            ->assertTableColumnFormattedStateSet('approval_status', 'Goedgekeurd', $accepted)
            ->assertTableColumnFormattedStateSet('approval_status', 'Afgewezen', $declined);
    }

    public function test_can_create_purchase_order_with_lines(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'creditor_name' => 'Acme Corp',
                'description' => 'Office supplies',
                'date' => '2026-06-26',
                'image_path' => UploadedFile::fake()->image('factuur.jpg'),
                'lines' => [
                    ['description' => 'Paper', 'price' => 100, 'price_vat' => 21, 'cost_center_id' => $costCenter->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'creditor_name' => 'Acme Corp',
            'description' => 'Office supplies',
            'status' => PurchaseOrderStatus::Open->value,
        ]);
    }

    public function test_can_create_purchase_order_without_cost_center(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'creditor_name' => 'Acme Corp',
                'description' => 'Office supplies',
                'date' => '2026-06-26',
                'image_path' => UploadedFile::fake()->image('factuur.jpg'),
                'lines' => [
                    ['description' => 'Paper', 'price' => 100, 'price_vat' => 21, 'cost_center_id' => null],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $purchaseOrder = PurchaseOrder::query()->sole();
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $purchaseOrder->id,
            'cost_center_id' => null,
        ]);
    }

    public function test_image_is_required_when_creating_a_purchase_order(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'creditor_name' => 'Acme Corp',
                'description' => 'Office supplies',
                'date' => '2026-06-26',
                'lines' => [
                    ['description' => 'Paper', 'price' => 100, 'price_vat' => 21, 'cost_center_id' => null],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['image_path']);
    }

    public function test_accept_approval_is_blocked_when_a_line_has_no_cost_center(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()->open()->withLines()->create();
        $purchaseOrder->lines()->update(['cost_center_id' => null]);

        Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
            ->callAction('acceptApproval')
            ->assertNotified();

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderStatus::Open, $purchaseOrder->status);
        static::assertSame(PurchaseOrderApprovalStatus::Pending, $purchaseOrder->approval_status);
        $this->assertDatabaseMissing('bookkeeping_records', ['reference_id' => $purchaseOrder->id]);
    }

    public function test_accept_approval_is_blocked_without_creditor_information(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()->open()->withoutCreditor()->withLines()->create();

        Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
            ->callAction('acceptApproval')
            ->assertNotified();

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderStatus::Open, $purchaseOrder->status);
        static::assertSame(PurchaseOrderApprovalStatus::Pending, $purchaseOrder->approval_status);
    }

    public function test_mark_as_paid_is_blocked_when_incomplete(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()->open()->withLines()->create();
        $purchaseOrder->lines()->update(['cost_center_id' => null]);
        $this->attachCompletedTransaction($purchaseOrder);

        try {
            app(PurchaseOrderService::class)->markAsPaid(new PurchaseOrderIdList([PurchaseOrderId::create($purchaseOrder->id)]));
            static::fail('Expected PurchaseOrderIncompleteException was not thrown.');
        } catch (PurchaseOrderIncompleteException) {
            static::assertSame(PurchaseOrderStatus::Open, $purchaseOrder->fresh()->status);
        }

        $this->assertDatabaseMissing('bookkeeping_records', ['reference_id' => $purchaseOrder->id]);
    }

    public function test_mark_as_paid_is_blocked_without_completed_transactions(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()->open()->withLines()->create();
        $purchaseOrder
            ->bankTransactions()
            ->attach(
                BankTransaction::factory()->create(),
            );

        try {
            app(PurchaseOrderService::class)->markAsPaid(new PurchaseOrderIdList([PurchaseOrderId::create($purchaseOrder->id)]));
            static::fail('Expected PurchaseOrderHasNoCompletedTransactionsException was not thrown.');
        } catch (PurchaseOrderHasNoCompletedTransactionsException) {
            static::assertSame(PurchaseOrderStatus::Open, $purchaseOrder->fresh()->status);
        }

        $this->assertDatabaseMissing('bookkeeping_records', ['reference_id' => $purchaseOrder->id]);
    }

    public function test_mark_as_paid_action_is_hidden_without_completed_transactions(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()
            ->open()
            ->withLines()
            ->create([
                'status' => PurchaseOrderStatus::Pending,
            ]);

        Livewire::test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
            ->assertActionHidden('markAsPaid');
    }

    public function test_mark_as_paid_action_is_visible_with_a_completed_transaction(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()
            ->open()
            ->withLines()
            ->create([
                'status' => PurchaseOrderStatus::Pending,
            ]);
        $this->attachCompletedTransaction($purchaseOrder);

        Livewire::test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
            ->assertActionVisible('markAsPaid');
    }

    public function test_accept_approval_does_not_create_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
            ->create(['status' => PurchaseOrderStatus::Open, 'date' => '2026-06-15']);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertActionHasLabel('acceptApproval', 'Goedkeuren')
            ->callAction('acceptApproval');

        $po->refresh();
        static::assertSame(PurchaseOrderApprovalStatus::Accepted, $po->approval_status);
        static::assertSame(PurchaseOrderStatus::Open, $po->status);

        $this->assertDatabaseMissing('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_mark_as_paid_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
            ->create(['status' => PurchaseOrderStatus::Pending, 'date' => '2026-06-15']);
        $this->attachCompletedTransaction($po);

        app(PurchaseOrderService::class)->markAsPaid(new PurchaseOrderIdList([PurchaseOrderId::create($po->id)]));

        $po->refresh();
        static::assertSame(PurchaseOrderStatus::Paid, $po->status);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_cannot_edit_purchase_order_when_not_open(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Pending]);

        static::assertFalse(Gate::allows('update', $po));
    }

    public function test_assigning_member_fills_creditor_name_and_iban(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->has(PaymentInformation::factory()->state([
            'banking_account_number' => 'NL02ABNA0123456789',
        ]))->createQuietly();
        $costCenter = CostCenter::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'member_id' => $member->id,
                'description' => 'Vlaggetjes voor de haven',
                'date' => '2026-09-23',
                'image_path' => UploadedFile::fake()->image('factuur.jpg'),
                'lines' => [
                    ['description' => 'Vlaggetjes', 'price' => 25, 'price_vat' => 5.25, 'cost_center_id' => $costCenter->id],
                ],
            ])
            ->assertFormSet([
                'creditor_iban' => 'NL02ABNA0123456789',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'member_id' => $member->id,
            'creditor_iban' => 'NL02ABNA0123456789',
        ]);
    }

    public function test_create_page_prefills_creditor_data_from_member_query_parameter(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->has(PaymentInformation::factory()->state([
            'banking_account_number' => 'NL02ABNA0123456789',
        ]))->createQuietly();
        $costCenter = CostCenter::factory()->create();

        Livewire::withQueryParams(['member_id' => (string) $member->id])
            ->test(CreatePurchaseOrder::class)
            ->assertFormSet([
                'member_id' => $member->id,
                'creditor_iban' => 'NL02ABNA0123456789',
            ])
            ->fillForm([
                'description' => 'Vlaggetjes voor de haven',
                'date' => '2026-09-23',
                'image_path' => UploadedFile::fake()->image('factuur.jpg'),
                'lines' => [
                    ['description' => 'Vlaggetjes', 'price' => 25, 'price_vat' => 5.25, 'cost_center_id' => $costCenter->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'member_id' => $member->id,
            'creditor_iban' => 'NL02ABNA0123456789',
        ]);
    }

    public function test_decline_approval_stores_reason_and_cancels_purchase_order(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->open()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertActionHasLabel('declineApproval', 'Afkeuren')
            ->callAction('declineApproval', data: ['declined_reason' => 'Niet geautoriseerd door penningmeester']);

        $po->refresh();
        static::assertSame(PurchaseOrderStatus::Cancelled, $po->status);
        static::assertSame(PurchaseOrderApprovalStatus::Declined, $po->approval_status);
        static::assertSame('Niet geautoriseerd door penningmeester', $po->declined_reason);
    }

    public function test_decline_approval_reason_is_required(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->open()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->callAction('declineApproval', data: ['declined_reason' => ''])
            ->assertHasFormErrors(['declined_reason']);
    }

    public function test_decline_approval_is_hidden_when_approval_was_already_declined(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->cancelled()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertActionHidden('declineApproval');
    }

    public function test_mark_as_payment_declined_stores_reason(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->pending()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertActionHasLabel('markAsPaymentDeclined', 'Markeer betaling als geweigerd')
            ->callAction('markAsPaymentDeclined', data: ['declined_reason' => 'Factuur overtrof het budget']);

        $po->refresh();
        static::assertSame(PurchaseOrderStatus::Declined, $po->status);
        static::assertSame('Factuur overtrof het budget', $po->declined_reason);
    }

    public function test_mark_as_payment_declined_reason_is_required(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->pending()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->callAction('markAsPaymentDeclined', data: ['declined_reason' => ''])
            ->assertHasFormErrors(['declined_reason']);
    }

    public function test_mark_as_payment_declined_is_hidden_when_not_sent_to_bank(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->open()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertActionHidden('markAsPaymentDeclined');
    }

    public function test_declined_reason_is_shown_on_form(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->declined('Te duur')->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertFormSet(['declined_reason' => 'Te duur']);
    }

    public function test_edit_form_shows_the_approval_status(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->accepted()->createQuietly();

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertFormSet(['approval_status' => PurchaseOrderApprovalStatus::Accepted->value])
            ->assertFormFieldDisabled('approval_status');
    }

    public function test_declined_reason_is_shown_for_an_approval_declined_purchase_order(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->cancelled('Afgewezen door het bestuur')->createQuietly();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->assertFormSet(['declined_reason' => 'Afgewezen door het bestuur'])
            ->assertFormFieldVisible('declined_reason');
    }

    public function test_edit_page_refresh_reloads_the_form_state(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->open()->createQuietly();

        $component = Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()]);
        $component->assertFormSet(['approval_status' => PurchaseOrderApprovalStatus::Pending->value]);

        $po->approval_status = PurchaseOrderApprovalStatus::Accepted;
        $po->saveQuietly();

        $component->call('refresh');

        $component->assertFormSet(['approval_status' => PurchaseOrderApprovalStatus::Accepted->value]);
    }

    public function test_view_page_refresh_reloads_the_form_state(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->pending()->createQuietly();

        $component = Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()]);
        $component->assertFormSet(['status' => PurchaseOrderStatus::Pending->value]);

        $po->status = PurchaseOrderStatus::Paid;
        $po->saveQuietly();

        $component->call('refresh');

        $component->assertFormSet(['status' => PurchaseOrderStatus::Paid->value]);
    }

    public function test_can_fill_notes_on_purchase_order(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->open()->create();

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->fillForm(['notes' => 'Gekocht voor het zeilkamp'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'notes' => 'Gekocht voor het zeilkamp']);
    }

    public function test_admin_sees_financial_relation_managers_on_purchase_order(): void
    {
        $this->withAuthorizedUser();
        $purchaseOrder = PurchaseOrder::factory()->open()->withLines()->create();

        $component = Livewire::test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])->instance();

        static::assertSame(
            [
                PurchaseOrderBankTransactionsRelationManager::class,
                PurchaseOrderBookkeepingRecordsRelationManager::class,
            ],
            array_values($component->getRelationManagers()),
        );
    }

    private function attachCompletedTransaction(PurchaseOrder $purchaseOrder): BankTransaction
    {
        $transaction = BankTransaction::factory()->create(['status' => BankTransactionStatus::Completed]);
        $purchaseOrder->bankTransactions()->attach($transaction);

        return $transaction;
    }
}
