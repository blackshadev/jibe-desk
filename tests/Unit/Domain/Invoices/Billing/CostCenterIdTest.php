<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing;

use App\Domain\Invoices\Billing\CostCenterId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class CostCenterIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return CostCenterId::class;
    }
}
