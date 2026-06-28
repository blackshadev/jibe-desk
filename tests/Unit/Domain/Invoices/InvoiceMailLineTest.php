<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceMailLine;
use Tests\UnitTestCase;

final class InvoiceMailLineTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $price = new CompoundPrice(25.0, 5.25);
        $subTotal = new CompoundPrice(50.0, 10.5);

        $subject = new InvoiceMailLine(
            description: 'Membership fee',
            quantity: 2.0,
            price: $price,
            subTotal: $subTotal,
        );

        static::assertSame('Membership fee', $subject->description);
        static::assertSame(2.0, $subject->quantity);
        static::assertSame($price, $subject->price);
        static::assertSame($subTotal, $subject->subTotal);
    }
}
