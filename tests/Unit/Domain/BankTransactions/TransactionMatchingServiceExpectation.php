<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\MatchCriteria;
use App\Domain\BankTransactions\MatchResult;
use App\Domain\BankTransactions\TransactionMatchingService;
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
}
