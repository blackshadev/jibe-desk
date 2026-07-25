<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;

final readonly class MatchResult
{
    private function __construct(
        public bool $isMatch,
        public ?InvoiceId $invoiceId = null,
        public ?PurchaseOrderId $purchaseOrderId = null,
    ) {}

    public static function foundInvoice(InvoiceId $invoiceId): self
    {
        return new self(isMatch: true, invoiceId: $invoiceId);
    }

    public static function foundPurchaseOrder(PurchaseOrderId $purchaseOrderId): self
    {
        return new self(isMatch: true, purchaseOrderId: $purchaseOrderId);
    }

    public static function none(): self
    {
        return new self(isMatch: false);
    }
}
