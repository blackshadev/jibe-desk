<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\NewInvoice;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class NewInvoiceTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $memberId = MemberId::create(42);
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $items = new BillableItemList([
            new BillableItem(BillableItemId::create(1), new CompoundPrice(10.0, 2.1), 1.0, 'Test', CostCenterId::create(1)),
        ]);
        $batchId = InvoiceBatchId::create(9);

        $subject = new NewInvoice(
            memberId: $memberId,
            invoiceDate: $invoiceDate,
            items: $items,
            batchId: $batchId,
        );

        static::assertSame($memberId, $subject->memberId);
        static::assertSame($invoiceDate, $subject->invoiceDate);
        static::assertSame($items, $subject->items);
        static::assertSame($batchId, $subject->batchId);
    }

    public function test_it_allows_null_batch_id(): void
    {
        $subject = new NewInvoice(
            memberId: MemberId::create(1),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            items: new BillableItemList([]),
        );

        static::assertNull($subject->batchId);
    }
}
