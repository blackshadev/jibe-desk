<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchService;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class InvoiceBatchServiceExpectation
{
    private function __construct(
        public MockInterface&InvoiceBatchService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceBatchService::class));
    }

    public function expectsCreateBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, InvoiceBatchId $return): void
    {
        $this->mock
            ->expects('createBatch')
            ->with(equalTo($invoiceDate), equalTo($sepaTransferDate))
            ->andReturn($return);
    }

    public function expectsAttachBatchMonth(InvoiceBatchId $id, int $return): void
    {
        $this->mock
            ->expects('attachBatchMonth')
            ->with(equalTo($id))
            ->andReturn($return);
    }

    public function expectsStartGeneration(InvoiceBatchId $id, int $expectedInvoices): void
    {
        $this->mock
            ->expects('startGeneration')
            ->with(equalTo($id), equalTo($expectedInvoices));
    }

    public function expectsFinishGeneration(InvoiceBatchId $id): void
    {
        $this->mock
            ->expects('finishGeneration')
            ->with(equalTo($id));
    }
}
