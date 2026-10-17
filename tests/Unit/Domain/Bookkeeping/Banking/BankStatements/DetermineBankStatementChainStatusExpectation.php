<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\Banking\BankStatements;

use App\Domain\Bookkeeping\Banking\BankStatements\DetermineBankStatementChainStatus;
use App\Domain\Bookkeeping\Banking\BankStatements\DetermineBankStatementChainStatusInput;
use App\Domain\Bookkeeping\Banking\BankStatements\StatementChainStatus;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class DetermineBankStatementChainStatusExpectation
{
    private function __construct(
        public MockInterface&DetermineBankStatementChainStatus $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(DetermineBankStatementChainStatus::class));
    }

    public function expectsDetermine(DetermineBankStatementChainStatusInput $input, StatementChainStatus $return): void
    {
        $this->mock
            ->expects('determine')
            ->with(equalTo($input))
            ->andReturn($return);
    }

    public function expectsDetermineAlways(StatementChainStatus $return): void
    {
        $this->mock
            ->shouldReceive('determine')
            ->andReturn($return);
    }

    public function expectsDetermineCapturingDates(array &$dates, StatementChainStatus $return): void
    {
        $this->mock
            ->shouldReceive('determine')
            ->andReturnUsing(static function (DetermineBankStatementChainStatusInput $input) use (&$dates, $return): StatementChainStatus {
                $dates[] = $input->startDate->format('Y-m-d');

                return $return;
            });
    }
}
