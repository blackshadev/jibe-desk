<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\SharedKernel\CompoundPrice;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;

final readonly class SepaExportPurchaseOrder
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public string $creditorName,
        public string $creditorIban,
        public CompoundPrice $total,
    ) {}

    public function reference(): string
    {
        return 'PO-' . $this->purchaseOrderId->value;
    }

    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }
}
