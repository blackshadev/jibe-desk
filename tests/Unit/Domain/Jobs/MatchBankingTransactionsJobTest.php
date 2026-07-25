<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Jobs;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\Jobs\MatchBankingTransactionsJob;
use Override;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\BankTransactions\BankTransactionRepositoryExpectation;
use Tests\Unit\Domain\BankTransactions\BankTransactionServiceExpectation;

final class MatchBankingTransactionsJobTest extends FeatureTestCase
{
    private BankTransactionRepositoryExpectation $repo;
    private BankTransactionServiceExpectation $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = BankTransactionRepositoryExpectation::create();
        $this->service = BankTransactionServiceExpectation::create();
    }

    public function test_job_fetches_unresolved_and_calls_service(): void
    {
        $id1 = BankTransactionId::create(1);
        $id2 = BankTransactionId::create(2);
        $idList = new BankTransactionIdList([$id1, $id2]);

        $this->repo->expectsGetUnresolvedIds(50, $idList);
        $this->service->expectsResolveMatching($idList);

        $job = new MatchBankingTransactionsJob();
        $job->handle($this->repo->mock, $this->service->mock);
    }

    public function test_job_skips_when_no_unresolved_ids(): void
    {
        $emptyList = new BankTransactionIdList([]);

        $this->repo->expectsGetUnresolvedIds(50, $emptyList);

        $this->service->expectsResolveMatchingNever();

        $job = new MatchBankingTransactionsJob();
        $job->handle($this->repo->mock, $this->service->mock);
    }

    public function test_job_respects_batch_size(): void
    {
        $idList = new BankTransactionIdList([BankTransactionId::create(1)]);

        $this->repo->expectsGetUnresolvedIds(25, $idList);
        $this->service->expectsResolveMatching($idList);

        $job = new MatchBankingTransactionsJob(batchSize: 25);
        $job->handle($this->repo->mock, $this->service->mock);
    }
}
