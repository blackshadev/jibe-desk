<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankAccounts;

use App\Domain\BankAccounts\BankAccountId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class BankAccountIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return BankAccountId::class;
    }
}
