<?php

declare(strict_types=1);

namespace App\Infrastructure\BankStatements;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankStatements\BankStatementId;
use App\Domain\BankStatements\BankStatementRepository;
use App\Domain\BankStatements\CreateBankStatement;
use App\Domain\BankStatements\PreviousStatement;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use App\Models\BankStatement;
use Override;

final readonly class BankStatementDbRepository implements BankStatementRepository
{
    #[Override]
    public function upsert(CreateBankStatement $dto): BankStatementId
    {
        $statement = BankStatement::query()->updateOrCreate(
            [
                'bank_account_id' => $dto->bankAccountId,
                'statement_number' => $dto->statementNumber,
            ],
            [
                'start_date' => $dto->startDate,
                'end_date' => $dto->endDate,
                'opening_balance' => $dto->openingBalance,
                'closing_balance' => $dto->closingBalance,
                'currency' => $dto->currency,
                'file_path' => $dto->filePath,
            ],
        );

        return BankStatementId::create($statement->id);
    }

    #[Override]
    public function findPrevious(BankAccountId $accountId, string $startDate): ?PreviousStatement
    {
        $statement = BankStatement::query()
            ->where('bank_account_id', $accountId->value)
            ->where('start_date', '<', $startDate)
            ->orderByDesc('start_date')
            ->first();

        if ($statement === null) {
            return null;
        }

        return new PreviousStatement(
            statementNumber: $statement->statement_number,
            closingBalance: (float) $statement->closing_balance,
        );
    }

    #[Override]
    public function updateIntegrity(BankStatementId $id, StatementIntegrityStatus $integrityStatus, float $difference): void
    {
        BankStatement::query()
            ->where('id', $id->value)
            ->update([
                'integrity_status' => $integrityStatus->value,
                'balance_difference' => $difference,
            ]);
    }

    #[Override]
    public function updateChain(BankStatementId $id, StatementChainStatus $chainStatus): void
    {
        BankStatement::query()
            ->where('id', $id->value)
            ->update(['chain_status' => $chainStatus->value]);
    }
}
