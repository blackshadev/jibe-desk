<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\SepaBatchEmailData;
use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchRepository;
use App\Domain\Invoices\SepaBatchStatus;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class SepaBatchRepositoryExpectation
{
    private function __construct(
        public MockInterface&SepaBatchRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(SepaBatchRepository::class));
    }

    public function expectsCreate(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, SepaBatchStatus $status, SepaBatchId $return): void
    {
        $this->mock
            ->expects('create')
            ->with(equalTo($invoiceDate), equalTo($sepaTransferDate), equalTo($status))
            ->andReturn($return);
    }

    public function expectsAddOpenInvoicesFromBatchMonth(SepaBatchId $batchId, int $return): void
    {
        $this->mock
            ->expects('addOpenInvoicesFromBatchMonth')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }

    public function expectsMarkGenerationStarted(SepaBatchId $batchId, int $expectedInvoices): void
    {
        $this->mock
            ->expects('markGenerationStarted')
            ->with(equalTo($batchId), equalTo($expectedInvoices));
    }

    public function expectsMarkGenerationFinished(SepaBatchId $batchId): void
    {
        $this->mock
            ->expects('markGenerationFinished')
            ->with(equalTo($batchId));
    }

    public function expectsMarkInvoicesAsPending(SepaBatchId $batchId): void
    {
        $this->mock
            ->expects('markInvoicesAsPending')
            ->with(equalTo($batchId));
    }

    public function expectsCloseBatch(SepaBatchId $batchId): void
    {
        $this->mock
            ->expects('closeBatch')
            ->with(equalTo($batchId));
    }

    public function expectsCompleteBatch(SepaBatchId $batchId): void
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

    /** @param list<InvoiceId> $return */
    public function expectsGetPendingInvoicesForBatch(SepaBatchId $batchId, array $return): void
    {
        $this->mock
            ->expects('getPendingInvoicesForBatch')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }

    public function expectsAttachInvoice(InvoiceId $invoiceId, SepaBatchId $batchId): void
    {
        $this->mock
            ->expects('attachInvoice')
            ->with(equalTo($batchId), equalTo($invoiceId));
    }

    public function expectsGetBatchEmailData(SepaBatchId $batchId, SepaBatchEmailData $return): void
    {
        $this->mock
            ->expects('getBatchEmailData')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }
}
