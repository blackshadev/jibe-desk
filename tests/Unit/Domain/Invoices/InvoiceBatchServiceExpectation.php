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

    public function expectsCreateBatch(DateTimeInterface $invoiceDate, InvoiceBatchId $return): void
    {
        $this->mock
            ->expects('createBatch')
            ->with(equalTo($invoiceDate))
            ->andReturn($return);
    }

    public function expectsAttachBatchMonth(InvoiceBatchId $id): void
    {
        $this->mock
            ->expects('attachBatchMonth')
            ->with(equalTo($id));
    }
}
