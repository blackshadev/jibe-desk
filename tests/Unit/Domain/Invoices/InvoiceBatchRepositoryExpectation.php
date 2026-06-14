<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class InvoiceBatchRepositoryExpectation
{
    private function __construct(
        public MockInterface&InvoiceBatchRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceBatchRepository::class));
    }

    public function expectsCreate(DateTimeInterface $invoiceDate, InvoiceBatchStatus $status, InvoiceBatchId $return): void
    {
        $this->mock
            ->expects('create')
            ->with(equalTo($invoiceDate), equalTo($status))
            ->andReturn($return);
    }

    public function expectsAddOpenInvoicesFromBatchMonth(InvoiceBatchId $batchId): void
    {
        $this->mock
            ->expects('addOpenInvoicesFromBatchMonth')
            ->with(equalTo($batchId));
    }

    public function expectsMarkInvoicesAsPending(InvoiceBatchId $batchId): void
    {
        $this->mock
            ->expects('markInvoicesAsPending')
            ->with(equalTo($batchId));
    }

    public function expectsCloseBatch(InvoiceBatchId $batchId): void
    {
        $this->mock
            ->expects('closeBatch')
            ->with(equalTo($batchId));
    }

    public function expectsCompleteBatch(InvoiceBatchId $batchId): void
    {
        $this->mock
            ->expects('completeBatch')
            ->with(equalTo($batchId));
    }

    public function expectsUpdateInvoiceStatus(InvoiceId $invoiceId, InvoiceStatus $status): void
    {
        $this->mock
            ->expects('updateInvoiceStatus')
            ->with(equalTo($invoiceId), equalTo($status));
    }
}
