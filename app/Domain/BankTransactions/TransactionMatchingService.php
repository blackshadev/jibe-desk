<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface TransactionMatchingService
{
    public function findMatch(MatchCriteria $criteria): MatchResult;

    public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId;
}
