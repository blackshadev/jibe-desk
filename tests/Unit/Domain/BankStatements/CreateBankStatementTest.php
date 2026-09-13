<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankStatements;

use App\Domain\BankStatements\CreateBankStatement;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class CreateBankStatementTest extends UnitTestCase
{
    public function test_it_creates_a_bank_statement_with_valid_values(): void
    {
        $startDate = new DateTimeImmutable('2023-01-01');
        $endDate = new DateTimeImmutable('2023-01-03');

        $statement = new CreateBankStatement(
            bankAccountId: 1,
            statementNumber: '1/1',
            startDate: $startDate,
            endDate: $endDate,
            openingBalance: 1000.0,
            closingBalance: 1300.0,
            currency: 'EUR',
            filePath: '/tmp/sample.mta',
        );

        static::assertSame(1, $statement->bankAccountId);
        static::assertSame('1/1', $statement->statementNumber);
        static::assertSame($startDate, $statement->startDate);
        static::assertSame($endDate, $statement->endDate);
        static::assertSame(1000.0, $statement->openingBalance);
        static::assertSame(1300.0, $statement->closingBalance);
        static::assertSame('EUR', $statement->currency);
        static::assertSame('/tmp/sample.mta', $statement->filePath);
    }
}
