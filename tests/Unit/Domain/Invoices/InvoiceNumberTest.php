<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceNumber;
use InvalidArgumentException;
use Tests\UnitTestCase;

final class InvoiceNumberTest extends UnitTestCase
{
    public function test_it_stores_and_returns_the_number(): void
    {
        $subject = new InvoiceNumber('I-2026000001');

        static::assertSame('I-2026000001', $subject->value);
        static::assertSame('I-2026000001', (string) $subject);
    }

    public function test_it_rejects_invalid_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InvoiceNumber('X-2026000001');
    }

    public function test_it_rejects_invalid_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InvoiceNumber('I-202600001');
    }
}
