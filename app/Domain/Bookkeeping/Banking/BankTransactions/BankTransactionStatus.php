<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankTransactions;

enum BankTransactionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
}
