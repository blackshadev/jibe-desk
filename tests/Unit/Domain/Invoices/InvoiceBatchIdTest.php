<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use Tests\Unit\Domain\NumericIdTestCase;

final class InvoiceBatchIdTest extends NumericIdTestCase
{
    protected function getSubject(): string
    {
        return InvoiceBatchId::class;
    }
}
