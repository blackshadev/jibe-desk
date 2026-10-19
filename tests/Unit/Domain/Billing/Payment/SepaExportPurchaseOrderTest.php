<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\SepaExportPurchaseOrder;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\SharedKernel\CompoundPrice;
use Tests\UnitTestCase;

final class SepaExportPurchaseOrderTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $purchaseOrderId = PurchaseOrderId::create(42);
        $total = new CompoundPrice(100.0, 21.0);

        $subject = new SepaExportPurchaseOrder(
            purchaseOrderId: $purchaseOrderId,
            creditorName: 'Supplier Ltd',
            creditorIban: 'NL91ABNA0417164300',
            total: $total,
        );

        static::assertSame($purchaseOrderId, $subject->purchaseOrderId);
        static::assertSame('Supplier Ltd', $subject->creditorName);
        static::assertSame('NL91ABNA0417164300', $subject->creditorIban);
        static::assertSame($total, $subject->total);
    }

    public function test_reference_is_prefixed_with_po(): void
    {
        $subject = new SepaExportPurchaseOrder(
            purchaseOrderId: PurchaseOrderId::create(7),
            creditorName: 'Supplier Ltd',
            creditorIban: 'NL91ABNA0417164300',
            total: new CompoundPrice(100.0, 21.0),
        );

        static::assertSame('PO-7', $subject->reference());
    }

    public function test_amount_in_cents_uses_net_price_only(): void
    {
        $subject = new SepaExportPurchaseOrder(
            purchaseOrderId: PurchaseOrderId::create(1),
            creditorName: 'Supplier Ltd',
            creditorIban: 'NL91ABNA0417164300',
            total: new CompoundPrice(99.95, 20.99),
        );

        static::assertSame(9995, $subject->amountInCents());
    }
}
