<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

enum BankingTransactionReversalState
{
    case Reversed;
    case Reversal;
    case None;
}
