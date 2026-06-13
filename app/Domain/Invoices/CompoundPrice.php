<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Formatters\PriceFormatter;
use Override;
use Stringable;

final readonly class CompoundPrice implements Stringable
{
    public function __construct(
        public float $price,
        public float $vat,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return PriceFormatter::format($this->price);
    }

    public static function empty(): self
    {
        return new self(0.0, 0.0);
    }

    public static function create(float $price, int $quantity = 1): self
    {
        return new self($price * $quantity, $price * 0.21 * $quantity);
    }

    public function add(self $second): self
    {
        return new self($this->price + $second->price, $this->vat + $second->vat);
    }
}
