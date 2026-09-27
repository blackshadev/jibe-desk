<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use RuntimeException;

final class PurchaseOrderHasNoCompletedTransactionsException extends RuntimeException
{
    public function __construct(
        public readonly PurchaseOrderId $purchaseOrderId,
    ) {
        parent::__construct("Purchase order {$purchaseOrderId->value} has no completed linked transactions");
    }
}
