<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\CreateBankTransaction;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BankTransactionRepositoryExpectation
{
    private function __construct(
        public MockInterface&BankTransactionRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BankTransactionRepository::class));
    }

    public function expectsExistsByHash(string $hash, bool $return): void
    {
        $this->mock
            ->expects('existsByHash')
            ->with(equalTo($hash))
            ->andReturn($return);
    }

    public function expectsExistsByHashAlways(bool $return): void
    {
        $this->mock
            ->shouldReceive('existsByHash')
            ->andReturn($return);
    }

    public function expectsCreate(CreateBankTransaction $dto, BankTransactionId $return): void
    {
        $this->mock
            ->expects('create')
            ->with(equalTo($dto))
            ->andReturn($return);
    }

    public function expectsCreateAlways(BankTransactionId $return): void
    {
        $this->mock
            ->shouldReceive('create')
            ->andReturn($return);
    }

    public function expectsCreateNever(): void
    {
        $this->mock
            ->shouldReceive('create')
            ->never();
    }
}
