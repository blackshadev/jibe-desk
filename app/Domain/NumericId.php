<?php

declare(strict_types=1);

namespace App\Domain;

use Webmozart\Assert\Assert;

abstract readonly class NumericId
{
    final public function __construct(
        public int $value,
    ) {
        Assert::greaterThan($value, 0);
    }

    final public static function create(int $value): static
    {
        return new static($value);
    }
}
