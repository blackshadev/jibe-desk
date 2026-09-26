<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

final readonly class PurchaseOrderProblems
{
    /** @param list<PurchaseOrderProblem> $problems */
    public function __construct(
        public array $problems = [],
    ) {}

    /** @throws PurchaseOrderIncompleteException */
    public function assertComplete(): void
    {
        if ($this->problems !== []) {
            throw new PurchaseOrderIncompleteException($this->problems);
        }
    }
}
