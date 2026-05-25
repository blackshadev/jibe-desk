<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use Tests\Unit\Domain\NumericIdTestCase;

final class InvoiceIdTest extends NumericIdTestCase
{
    protected function getSubject(): string
    {
        return InvoiceId::class;
    }
}
