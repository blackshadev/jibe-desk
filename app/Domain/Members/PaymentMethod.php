<?php

declare(strict_types=1);

namespace App\Domain\Members;

enum PaymentMethod: string
{
    case DirectDebit = 'direct_debit';
    case Transfer = 'transfer';
}
