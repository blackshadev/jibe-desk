<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters;

use App\Domain\Bookkeeping\CostCenters\CostCenterGroupSubtree;
use Tests\UnitTestCase;

final class CostCenterGroupSubtreeTest extends UnitTestCase
{
    public function test_it_contains_a_group_from_its_own_subtree(): void
    {
        $subject = new CostCenterGroupSubtree([20, 21, 25]);

        static::assertTrue($subject->contains(20));
        static::assertTrue($subject->contains(21));
        static::assertTrue($subject->contains(25));
    }

    public function test_it_does_not_contain_a_group_outside_its_subtree(): void
    {
        $subject = new CostCenterGroupSubtree([20, 21, 25]);

        static::assertFalse($subject->contains(30));
    }

    public function test_an_empty_subtree_contains_nothing(): void
    {
        $subject = new CostCenterGroupSubtree([]);

        static::assertFalse($subject->contains(20));
    }
}
