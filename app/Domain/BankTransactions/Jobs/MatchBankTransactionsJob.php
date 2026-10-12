<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions\Jobs;

use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\Jobs\BaseJob;

final class MatchBankTransactionsJob extends BaseJob
{
    public function __construct(
        public int $batchSize = 50,
    ) {}

    public function handle(
        BankTransactionRepository $bankTransactionRepository,
        BankTransactionService $bankTransactionService,
    ): void {
        $unresolvedIds = $bankTransactionRepository->getUnresolvedIds($this->batchSize);

        if (count($unresolvedIds->ids) === 0) {
            return;
        }

        $bankTransactionService->resolveMatching($unresolvedIds);
    }
}
