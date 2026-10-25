<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\Readiness\ExportReadinessReport;
use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\SepaBatchId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class ExportReadinessServiceExpectation
{
    private function __construct(
        public MockInterface&ExportReadinessService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ExportReadinessService::class));
    }

    public function expectsAssess(SepaBatchId $batchId, ExportReadinessReport $return): void
    {
        $this->mock
            ->expects('assess')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }

    public function neverExpectsAssess(): void
    {
        $this->mock->expects('assess')->never();
    }
}
