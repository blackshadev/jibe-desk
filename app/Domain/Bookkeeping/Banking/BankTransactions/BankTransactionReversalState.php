<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankTransactions;

enum BankTransactionReversalState
{
    case Reversed;
    case Reversal;
    case None;
}
