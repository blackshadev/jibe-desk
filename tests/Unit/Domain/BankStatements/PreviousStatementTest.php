<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankStatements;

use App\Domain\BankStatements\PreviousStatement;
use Tests\UnitTestCase;

final class PreviousStatementTest extends UnitTestCase
{
    public function test_it_holds_previous_statement_values(): void
    {
        $previousStatement = new PreviousStatement(
            statementNumber: '1/1',
            closingBalance: 1000.0,
        );

        static::assertSame('1/1', $previousStatement->statementNumber);
        static::assertSame(1000.0, $previousStatement->closingBalance);
    }
}
