<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class InvoiceIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return InvoiceId::class;
    }
}
