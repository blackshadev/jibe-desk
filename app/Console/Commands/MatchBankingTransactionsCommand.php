<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Jobs\MatchBankingTransactionsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:match-banking-transactions {--batch-size=50}')]
#[Description('Dispatch batch job to match unresolved banking transactions')]
final class MatchBankingTransactionsCommand extends Command
{
    public function handle(): void
    {
        MatchBankingTransactionsJob::dispatch(
            batchSize: (int) $this->option('batch-size'),
        );

        $this->info('MatchBankingTransactionsJob dispatched.');
    }
}
