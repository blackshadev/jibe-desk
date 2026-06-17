<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

final readonly class InvoiceMailLine
{
    public function __construct(
        public string $description,
        public float $quantity,
        public CompoundPrice $price,
        public CompoundPrice $subTotal,
    ) {}
}
