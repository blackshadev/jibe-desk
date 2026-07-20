<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

enum BankTransactionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
}
