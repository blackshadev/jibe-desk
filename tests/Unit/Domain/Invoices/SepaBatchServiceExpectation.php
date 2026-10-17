<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchService;
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
            ->expects('attachBatchMonth')
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
