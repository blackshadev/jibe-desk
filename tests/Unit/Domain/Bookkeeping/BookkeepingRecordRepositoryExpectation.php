<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
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

    public function expectsCreateForPurchaseOrder(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('createForPurchaseOrder')
            ->with(equalTo($ids));
    }

    public function expectsCreateForInvoice(InvoiceIdList $ids): void
    {
        $this->mock
            ->expects('createForInvoice')
            ->with(equalTo($ids));
    }
}
