<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface CostCenterBudgetRepository
{
    /**
     * @return int the number of budgets created
     */
    public function createZeroBudgetsForYear(int $year): int;
}
