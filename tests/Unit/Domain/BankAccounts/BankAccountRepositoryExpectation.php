<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankAccounts;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankAccounts\BankAccountRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BankAccountRepositoryExpectation
{
    private function __construct(
        public MockInterface&BankAccountRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BankAccountRepository::class));
    }

    public function expectsGetOwnAccounts(array $accounts): void
    {
        $this->mock
            ->expects('getOwnAccounts')
            ->andReturn($accounts);
    }

    public function expectsGetByIban(string $iban, BankAccountId $return): void
    {
        $this->mock
            ->shouldReceive('getByIban')
            ->with(equalTo($iban))
            ->andReturn($return);
    }

    public function defaultsGetOwnAccounts(array $array): void
    {
        $this->mock
            ->shouldReceive('getOwnAccounts')
            ->andReturn($array);
    }
}
