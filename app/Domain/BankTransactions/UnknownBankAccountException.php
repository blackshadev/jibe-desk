<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use RuntimeException;

final class UnknownBankAccountException extends RuntimeException
{
    public function __construct(
        public readonly string $accountNumber,
    ) {
        parent::__construct("Unknown bank account: {$accountNumber}");
    }
}
