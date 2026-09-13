<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface DetermineBankStatementChainStatus
{
    public function determine(DetermineBankStatementChainStatusInput $input): StatementChainStatus;
}
