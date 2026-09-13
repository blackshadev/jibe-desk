<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

use DateTimeInterface;

final readonly class CreateBankStatement
{
    public function __construct(
        public int $bankAccountId,
        public string $statementNumber,
        public DateTimeInterface $startDate,
        public DateTimeInterface $endDate,
        public float $openingBalance,
        public float $closingBalance,
        public string $currency,
        public string $filePath,
    ) {}
}
