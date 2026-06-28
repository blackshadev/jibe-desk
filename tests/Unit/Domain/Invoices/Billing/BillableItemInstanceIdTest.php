<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class BillableItemInstanceIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return BillableItemInstanceId::class;
    }
}
