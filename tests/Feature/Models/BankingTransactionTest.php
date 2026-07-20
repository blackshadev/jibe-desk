<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankingTransaction;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Tests\FeatureTestCase;

final class BankingTransactionTest extends FeatureTestCase
{
    public function test_unmatched_amount_returns_full_amount_when_no_references(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => 150.500]);

        $result = $transaction->unmatched_amount;

        static::assertSame(150.5, $result);
    }

    public function test_unmatched_amount_subtracts_invoice_totals_from_pivot(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => 200.000]);

        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()->forMember($member)->createQuietly();
        InvoiceLine::factory()->createQuietly(['invoice_id' => $invoice->id, 'price' => 50.000, 'quantity' => 1]);
        InvoiceLine::factory()->createQuietly(['invoice_id' => $invoice->id, 'price' => 25.000, 'quantity' => 1]);

        $transaction->invoices()->attach($invoice->id);

        $result = $transaction->unmatched_amount;

        // 200.0 - (50.0 + 25.0) = 200.0 - 75.0 = 125.0
        static::assertSame(125.0, $result);
    }

    public function test_unmatched_amount_adds_purchase_order_totals_from_pivot(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => -100.000]);

        $po = PurchaseOrder::factory()->createQuietly();
        PurchaseOrderLine::factory()->createQuietly(['purchase_order_id' => $po->id, 'price' => 30.000]);
        PurchaseOrderLine::factory()->createQuietly(['purchase_order_id' => $po->id, 'price' => 20.000]);

        $transaction->purchaseOrders()->attach($po->id);

        $result = $transaction->unmatched_amount;

        // -100.0 - 0 + (30.0 + 20.0) = -100.0 + 50.0 = -50.0
        static::assertSame(-50.0, $result);
    }

    public function test_unmatched_amount_returns_zero_when_fully_matched(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => 100.000]);

        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()->forMember($member)->createQuietly();
        InvoiceLine::factory()->createQuietly(['invoice_id' => $invoice->id, 'price' => 60.000, 'quantity' => 1]);
        InvoiceLine::factory()->createQuietly(['invoice_id' => $invoice->id, 'price' => 40.000, 'quantity' => 1]);

        $transaction->invoices()->attach($invoice->id);

        $result = $transaction->unmatched_amount;

        static::assertSame(0.0, $result);
    }

    public function test_status_defaults_to_open(): void
    {
        $transaction = BankingTransaction::factory()->createQuietly();

        static::assertSame(BankTransactionStatus::Open, $transaction->status);
    }

    public function test_is_completed_returns_true_when_status_is_completed(): void
    {
        $transaction = BankingTransaction::factory()->completed()->createQuietly();

        static::assertTrue($transaction->isCompleted());
    }

    public function test_is_completed_returns_false_when_status_is_open(): void
    {
        $transaction = BankingTransaction::factory()->createQuietly();

        static::assertFalse($transaction->isCompleted());
    }
}
