<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

enum ResolveStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Unresolvable = 'unresolvable';
}
