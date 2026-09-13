<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankStatements;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankStatements\BankStatementId;
use App\Domain\BankStatements\DetermineBankStatementChainStatusInput;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class DetermineBankStatementChainStatusInputTest extends UnitTestCase
{
    public function test_it_holds_the_input_for_chain_status_determination(): void
    {
        $id = BankStatementId::create(1);
        $accountId = BankAccountId::create(2);
        $startDate = new DateTimeImmutable('2023-01-01');

        $input = new DetermineBankStatementChainStatusInput(
            id: $id,
            accountId: $accountId,
            startDate: $startDate,
            openingBalance: 1000.0,
        );

        static::assertSame($id, $input->id);
        static::assertSame($accountId, $input->accountId);
        static::assertSame($startDate, $input->startDate);
        static::assertSame(1000.0, $input->openingBalance);
    }
}
