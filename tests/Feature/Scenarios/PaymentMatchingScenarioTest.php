<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\PurchaseOrder;
use Override;
use Tests\Concerns\TestsBankingLifecycle;
use Tests\Concerns\TestsMT940;

final class PaymentMatchingScenarioTest extends MemberLifecycleScenario
{
    use TestsBankingLifecycle;
    use TestsMT940;

    private const string MEMBER_IBAN = 'NL91ABNA0417164300';
    private const string CREDITOR_IBAN = 'NL02INGB0002447973';

    #[Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->travelToDate('2026-08-15');
    }

    public function test_an_invoice_and_purchase_order_are_settled_by_imported_transactions(): void
    {
        $member = Member::factory()
            ->has(PaymentInformation::factory()->state([
                'banking_account_number' => self::MEMBER_IBAN,
                'banking_bic' => 'ABNANL2A',
            ]))
            ->createOneQuietly();


        // Step 2 — an invoice with explicit lines is created and marked pending (which books it).
        $invoice = $this->createInvoice($member, [
            ['description' => 'Lidmaatschap Windsurfer (volwassenen)', 'price' => 68.00, 'quantity' => 1],
            ['description' => 'Vrijwilligersbijdrage', 'price' => 20.00, 'quantity' => 1],
        ], ['date' => '2026-08-15']);
        static::assertEqualsWithDelta(88.00, $invoice->total->price, 0.001);

        $this->markInvoiceAsPending($invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        // Step 3 — a purchase order is created and approved (approved == pending).
        $purchaseOrder = $this->createPurchaseOrder([
            'creditor_name' => 'Sloepenhandel BV',
            'creditor_iban' => self::CREDITOR_IBAN,
            'date' => '2026-08-15',
        ]);

        $this->approvePurchaseOrder($purchaseOrder);
        $purchaseOrder->refresh();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => PurchaseOrderStatus::Pending->value,
        ]);
        static::assertEqualsWithDelta(150.00, $purchaseOrder->total->price, 0.001);

        // Step 4 — the bank statement arrives: the member pays the invoice, the club pays the purchase order.
        $this->travelToDate('2026-08-20');
        $bankAccount = BankAccount::factory()->create();

        $this->importMt940Statement($this->mt940Statement($bankAccount, [
            [
                'date' => '2026-08-20',
                'debitCredit' => 'C',
                'amount' => 88.00,
                'counterpartyIban' => self::MEMBER_IBAN,
                'description' => 'Lidmaatschap augustus',
            ],
            [
                'date' => '2026-08-20',
                'debitCredit' => 'D',
                'amount' => 150.00,
                'counterpartyIban' => self::CREDITOR_IBAN,
                'description' => 'Inkoop bootmateriaal',
            ],
        ]));

        static::assertSame(2, BankTransaction::query()->count());
        $this->assertDatabaseHas('bank_statements', ['bank_account_id' => $bankAccount->id]);

        // Step 5 — matching linked both transactions automatically, without settling anything yet.
        $creditTransaction = BankTransaction::query()->where('amount', '>', 0)->sole();
        $debitTransaction = BankTransaction::query()->where('amount', '<', 0)->sole();

        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $creditTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $debitTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $creditTransaction->id,
            'resolve_status' => ResolveStatus::Resolved->value,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'id' => $debitTransaction->id,
            'resolve_status' => ResolveStatus::Resolved->value,
        ]);

        $creditTransaction->refresh();
        $debitTransaction->refresh();
        static::assertEqualsWithDelta(0.0, $creditTransaction->unmatched_amount, 0.001);
        static::assertEqualsWithDelta(0.0, $debitTransaction->unmatched_amount, 0.001);

        // Step 6 — completing the member's payment settles the invoice.
        $this->completeTransaction($creditTransaction);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $creditTransaction->id,
            'status' => BankTransactionStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'bank_transaction_id' => $creditTransaction->id,
        ]);

        // Step 7 — completing the outgoing payment settles the purchase order.
        $this->completeTransaction($debitTransaction);

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $debitTransaction->id,
            'status' => BankTransactionStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => PurchaseOrderStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
            'bank_transaction_id' => $debitTransaction->id,
        ]);
    }
}
