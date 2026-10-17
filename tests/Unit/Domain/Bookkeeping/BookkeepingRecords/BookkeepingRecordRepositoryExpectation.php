<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\BookkeepingRecords;

use App\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepository;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
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

    public function expectsCreateForBatch(SepaBatchId $batchId): void
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

    public function neverExpectsCreateForPurchaseOrder(): void
    {
        $this->mock->expects('createForPurchaseOrder')->never();
    }

    public function neverExpectsCreateForInvoice(): void
    {
        $this->mock->expects('createForInvoice')->never();
    }
}
