<?php

declare(strict_types=1);

namespace App\Domain\BankAccounts;

use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;

final readonly class BankAccountBalanceOverview
{
    public function __construct(
        public int $bankAccountId,
        public string $name,
        public string $iban,
        public ?float $openingAmount,
        public float $inflow,
        public float $outflow,
        public ?float $expectedClosing,
        public ?string $lastStatementDate,
        public ?StatementIntegrityStatus $statementIntegrityStatus,
        public ?StatementChainStatus $statementChainStatus,
    ) {}
}
