<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankTransactions;

enum ResolveStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Unresolvable = 'unresolvable';
}
