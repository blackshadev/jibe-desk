<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Jobs\MatchBankTransactionsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:match-bank-transactions {--batch-size=50}')]
#[Description('Dispatch batch job to match unresolved bank transactions')]
final class MatchBankTransactionsCommand extends Command
{
    public function handle(): void
    {
        MatchBankTransactionsJob::dispatch(
            batchSize: (int) $this->option('batch-size'),
        );

        $this->info('MatchBankTransactionsJob dispatched.');
    }
}
