<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

use Override;

final readonly class DetermineBankStatementChainStatusImpl implements DetermineBankStatementChainStatus
{
    public function __construct(
        private BankStatementRepository $repository,
    ) {}

    #[Override]
    public function determine(DetermineBankStatementChainStatusInput $input): StatementChainStatus
    {
        $previous = $this->repository->findPrevious($input->accountId, $input->startDate);

        if ($previous !== null) {
            $status = abs($previous->closingBalance - $input->openingBalance) < 0.01
                ? StatementChainStatus::Ok
                : StatementChainStatus::Broken;

            $this->repository->updateChain($input->id, $status);

            return $status;
        }

        $this->repository->updateChain($input->id, StatementChainStatus::Baseline);

        return StatementChainStatus::Baseline;
    }
}
