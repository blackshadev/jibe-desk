<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankStatements;

use App\Domain\Bookkeeping\Banking\BankAccounts\BankAccountId;
use DateTimeInterface;

final readonly class DetermineBankStatementChainStatusInput
{
    public function __construct(
        public BankStatementId $id,
        public BankAccountId $accountId,
        public DateTimeInterface $startDate,
        public float $openingBalance,
    ) {}
}
