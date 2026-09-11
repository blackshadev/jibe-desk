<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankStatements;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankStatements\BankStatementId;
use App\Domain\BankStatements\BankStatementRepository;
use App\Domain\BankStatements\CreateBankStatement;
use App\Domain\BankStatements\PreviousStatement;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BankStatementRepositoryExpectation
{
    private function __construct(
        public MockInterface&BankStatementRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BankStatementRepository::class));
    }

    public function expectsUpsert(CreateBankStatement $dto, BankStatementId $return): void
    {
        $this->mock
            ->expects('upsert')
            ->with(equalTo($dto))
            ->andReturn($return);
    }

    public function expectsFindPrevious(BankAccountId $accountId, string $startDate, ?PreviousStatement $return): void
    {
        $this->mock
            ->shouldReceive('findPrevious')
            ->with(equalTo($accountId), equalTo($startDate))
            ->andReturn($return);
    }

    public function expectsUpdateChain(BankStatementId $id, StatementChainStatus $status): void
    {
        $this->mock
            ->expects('updateChain')
            ->with(equalTo($id), equalTo($status));
    }

    public function expectsUpdateIntegrity(BankStatementId $id, StatementIntegrityStatus $status, float $difference): void
    {
        $this->mock
            ->expects('updateIntegrity')
            ->with(equalTo($id), equalTo($status), equalTo($difference));
    }

    public function expectsUpsertAlways(BankStatementId $return): void
    {
        $this->mock
            ->shouldReceive('upsert')
            ->andReturn($return);
    }

    public function expectsFindPreviousAlways(?PreviousStatement $return): void
    {
        $this->mock
            ->shouldReceive('findPrevious')
            ->andReturn($return);
    }

    public function expectsFindPreviousCapturingDates(array &$dates): void
    {
        $this->mock
            ->shouldReceive('findPrevious')
            ->andReturnUsing(static function (BankAccountId $id, string $startDate) use (&$dates): null {
                $dates[] = $startDate;

                return null;
            });
    }

    public function expectsUpdateChainAlways(): void
    {
        $this->mock
            ->shouldReceive('updateChain')
            ->andReturnNull();
    }

    public function expectsUpdateIntegrityNever(): void
    {
        $this->mock
            ->shouldReceive('updateIntegrity')
            ->never();
    }
}
