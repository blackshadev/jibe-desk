<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankStatements;

use App\Domain\Bookkeeping\Banking\BankAccounts\BankAccountId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankStatementRepository
{
    public function upsert(CreateBankStatement $dto): BankStatementId;

    public function findPrevious(BankAccountId $accountId, DateTimeInterface $startDate): ?PreviousStatement;

    public function updateIntegrity(BankStatementId $id, StatementIntegrityStatus $integrityStatus, float $difference): void;

    public function updateChain(BankStatementId $id, StatementChainStatus $chainStatus): void;
}
