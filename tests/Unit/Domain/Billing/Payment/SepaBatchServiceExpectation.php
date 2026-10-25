<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class SepaBatchServiceExpectation
{
    private function __construct(
        public MockInterface&SepaBatchService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(SepaBatchService::class));
    }

    public function expectsCreateBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, SepaBatchId $return): void
    {
        $this->mock
            ->expects('createBatch')
            ->with(equalTo($invoiceDate), equalTo($sepaTransferDate))
            ->andReturn($return);
    }

    public function expectsAttachBatchMonth(SepaBatchId $id, int $return): void
    {
        $this->mock
            ->expects('attachBatchMonthInvoices')
            ->with(equalTo($id))
            ->andReturn($return);
    }

    public function expectsAttachBatchMonthPurchaseOrders(SepaBatchId $id, int $return): void
    {
        $this->mock
            ->expects('attachBatchMonthPurchaseOrders')
            ->with(equalTo($id))
            ->andReturn($return);
    }

    public function expectsStartGeneration(SepaBatchId $id, int $expectedInvoices): void
    {
        $this->mock
            ->expects('startGeneration')
            ->with(equalTo($id), equalTo($expectedInvoices));
    }

    public function expectsFinishGeneration(SepaBatchId $id): void
    {
        $this->mock
            ->expects('finishGeneration')
            ->with(equalTo($id));
    }
}
