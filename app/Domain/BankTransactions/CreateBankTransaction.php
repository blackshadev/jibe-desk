<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

final readonly class CreateBankTransaction
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $description,
        public string $bankingAccountNumber,
        public string $importHash,
    ) {}
}
