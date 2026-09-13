<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

use App\Domain\BankAccounts\BankAccountId;
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
