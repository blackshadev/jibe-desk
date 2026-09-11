<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

final readonly class CreateBankStatement
{
    public function __construct(
        public int $bankAccountId,
        public string $statementNumber,
        public string $startDate,
        public string $endDate,
        public float $openingBalance,
        public float $closingBalance,
        public string $currency,
        public string $filePath,
    ) {}
}
