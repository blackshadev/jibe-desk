<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemId;
use Tests\Unit\Domain\NumericIdTestCase;

final class BillableItemIdTest extends NumericIdTestCase
{
    protected function getSubject(): string
    {
        return BillableItemId::class;
    }
}
