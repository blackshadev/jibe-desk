<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceLineId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class InvoiceLineIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return InvoiceLineId::class;
    }
}
