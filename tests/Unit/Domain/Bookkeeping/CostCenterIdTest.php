<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping;

use App\Domain\Bookkeeping\CostCenterId;
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
