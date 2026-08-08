<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionService;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BankTransactionServiceExpectation
{
    private function __construct(
        public MockInterface&BankTransactionService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BankTransactionService::class));
    }

    public function expectsResolveMatching(BankTransactionIdList $ids): void
    {
        $this->mock
            ->expects('resolveMatching')
            ->with(equalTo($ids));
    }

    public function expectsResolveMatchingNever(): void
    {
        $this->mock
            ->expects('resolveMatching')
            ->never();
    }

    public function expectsLinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
    {
        $this->mock
            ->expects('linkReversal')
            ->with(equalTo($reversalId), equalTo($originalId));
    }

    public function expectsUnlinkReversal(BankTransactionId $reversalId): void
    {
        $this->mock
            ->expects('unlinkReversal')
            ->with(equalTo($reversalId));
    }
}
