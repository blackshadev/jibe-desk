<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\MatchResult;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use Tests\UnitTestCase;

final class MatchResultTest extends UnitTestCase
{
    public function test_found_invoice(): void
    {
        $invoiceId = InvoiceId::create(42);
        $result = MatchResult::foundInvoice($invoiceId);

        static::assertTrue($result->isMatch);
        static::assertEquals($invoiceId, $result->invoiceId);
        static::assertNull($result->purchaseOrderId);
    }

    public function test_found_purchase_order(): void
    {
        $purchaseOrderId = PurchaseOrderId::create(7);
        $result = MatchResult::foundPurchaseOrder($purchaseOrderId);

        static::assertTrue($result->isMatch);
        static::assertNull($result->invoiceId);
        static::assertEquals($purchaseOrderId, $result->purchaseOrderId);
    }

    public function test_none(): void
    {
        $result = MatchResult::none();

        static::assertFalse($result->isMatch);
        static::assertNull($result->invoiceId);
        static::assertNull($result->purchaseOrderId);
    }
}
