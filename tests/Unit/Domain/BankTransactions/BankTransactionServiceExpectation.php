<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

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
}
