<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use Tests\FeatureTestCase;

final class InvoiceTest extends FeatureTestCase
{
    public function test_open_or_pending_scope_filters_correct_statuses(): void
    {
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

    public function test_order_by_amount_proximity_scope_orders_correctly(): void
    {
        $member = Member::factory()->createQuietly();

        $invoice1 = Invoice::factory()->forMember($member)
            ->has(InvoiceLine::factory()->state([ 'price' => 90.00, 'quantity' => 1]), 'lines')
            ->createQuietly(['status' => InvoiceStatus::Open]);


        $invoice2 = Invoice::factory()
            ->forMember($member)
            ->has(InvoiceLine::factory()->state(['price' => 110.00, 'quantity' => 1]), 'lines')
            ->createQuietly(['status' => InvoiceStatus::Open]);


        $invoice3 = Invoice::factory()
            ->forMember($member)
            ->has(InvoiceLine::factory()->state(['price' => 500.00, 'quantity' => 1]), 'lines')
            ->createQuietly(['status' => InvoiceStatus::Open]);


        $results = Invoice::query()
            ->openOrPending()
            ->orderByAmountProximity(110.00)
            ->pluck('id')
            ->all();

        static::assertSame([$invoice2->id, $invoice1->id, $invoice3->id], $results);
    }
}
