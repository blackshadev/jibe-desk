<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters;

use App\Domain\Bookkeeping\CostCenters\CostCenterGroupCycleException;
use Tests\UnitTestCase;

final class CostCenterGroupCycleExceptionTest extends UnitTestCase
{
    public function test_it_names_the_offending_group(): void
    {
        $exception = CostCenterGroupCycleException::forGroup('20');

        static::assertSame('Cost group "20" cannot become its own ancestor.', $exception->getMessage());
    }
}
