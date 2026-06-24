<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\InvoiceBatchId;
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
}
