<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\Banking\BankTransactions;

use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use App\Domain\Bookkeeping\Banking\BankTransactions\MatchCriteria;
use App\Domain\Bookkeeping\Banking\BankTransactions\MatchResult;
use App\Domain\Bookkeeping\Banking\BankTransactions\TransactionMatchingService;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class TransactionMatchingServiceExpectation
{
    private function __construct(
        public MockInterface&TransactionMatchingService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(TransactionMatchingService::class));
    }

    public function expectsFindMatch(MatchCriteria $criteria, MatchResult $return): void
    {
        $this->mock
            ->expects('findMatch')
            ->with(equalTo($criteria))
            ->andReturn($return);
    }

    public function expectsFindReversalMatch(MatchCriteria $criteria, ?BankTransactionId $return): void
    {
        $this->mock
            ->expects('findReversalMatch')
            ->with(equalTo($criteria))
            ->andReturn($return);
    }
}
