<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use RuntimeException;

final class PurchaseOrderIncompleteException extends RuntimeException
{
    /** @param list<PurchaseOrderProblem> $problems */
    public function __construct(
        public readonly array $problems,
    ) {
        parent::__construct('Purchase order is incomplete and cannot change status.');
    }
}
