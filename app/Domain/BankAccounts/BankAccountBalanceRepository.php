<?php

declare(strict_types=1);

namespace App\Domain\BankAccounts;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankAccountBalanceRepository
{
    /** @return list<BankAccountBalanceOverview> */
    public function getOverviewForYear(int $year): array;
}
