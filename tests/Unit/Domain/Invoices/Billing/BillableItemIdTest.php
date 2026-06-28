<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItemId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class BillableItemIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return BillableItemId::class;
    }
}
