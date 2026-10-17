<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\Banking\BankStatements;

use App\Domain\Bookkeeping\Banking\BankStatements\BankStatementId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class BankStatementIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return BankStatementId::class;
    }
}
