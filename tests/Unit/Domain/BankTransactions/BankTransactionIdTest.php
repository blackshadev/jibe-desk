<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class BankTransactionIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return BankTransactionId::class;
    }
}
