<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use RuntimeException;

final class CouldNotCompleteTransaction extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Cannot complete banking transaction: unmatched amount must be 0.',
        );
    }
}
