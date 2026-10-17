<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\SharedKernel\CompoundPrice;

final readonly class InvoiceMailLine
{
    public function __construct(
        public string $description,
        public float $quantity,
        public CompoundPrice $price,
        public CompoundPrice $subTotal,
    ) {}
}
