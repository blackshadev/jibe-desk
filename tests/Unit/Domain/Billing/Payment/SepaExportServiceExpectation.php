<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaExport;
use App\Domain\Billing\Payment\SepaExportService;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class SepaExportServiceExpectation
{
    private function __construct(
        public MockInterface&SepaExportService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(SepaExportService::class));
    }

    public function expectsExport(SepaBatchId $batchId, SepaExport $return): void
    {
        $this->mock
            ->expects('export')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }

    public function neverExpectsExport(): void
    {
        $this->mock->expects('export')->never();
    }
}
