<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use DateTimeInterface;

final readonly class MatchCriteria
{
    public function __construct(
        public DateTimeInterface $date,
        public float $amount,
        public string $bankingAccountNumber,
        public string $description,
    ) {}
}
