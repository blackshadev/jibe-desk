<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankStatements;

use App\Domain\BankStatements\BankStatementId;
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
