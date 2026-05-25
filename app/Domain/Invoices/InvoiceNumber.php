<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use Stringable;
use Webmozart\Assert\Assert;

final readonly class InvoiceNumber implements Stringable
{
    public function __construct(public string $value)
    {
        Assert::startsWith($value, 'I-');
        Assert::length($value, 12);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
