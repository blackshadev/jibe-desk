<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\SepaExportInvoice;
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

    public function expectsGetBatchDate(InvoiceBatchId $batchId, DateTimeInterface $return): void
    {
        $this->mock
            ->expects('getBatchDate')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }

    /** @param list<SepaExportInvoice> $return */
    public function expectsGetInvoicesForExport(InvoiceBatchId $batchId, array $return): void
    {
        $this->mock
            ->expects('getInvoicesForExport')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }
}
