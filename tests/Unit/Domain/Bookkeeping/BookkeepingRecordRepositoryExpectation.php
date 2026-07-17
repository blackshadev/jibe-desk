<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BookkeepingRecordRepositoryExpectation
{
    private function __construct(
        public MockInterface&BookkeepingRecordRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BookkeepingRecordRepository::class));
    }

    public function expectsCreateForBatch(InvoiceBatchId $batchId): void
    {
        $this->mock
            ->expects('createForBatch')
            ->with(equalTo($batchId));
    }

    public function expectsCreateForPurchaseOrder(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('createForPurchaseOrder')
            ->with(equalTo($id));
    }

    public function expectsCreateForInvoice(InvoiceId $id): void
    {
        $this->mock
            ->expects('createForInvoice')
            ->with(equalTo($id));
    }
}
