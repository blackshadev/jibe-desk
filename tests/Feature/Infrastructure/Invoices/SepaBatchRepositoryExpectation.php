<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchRepository;
use App\Domain\Invoices\SepaExportInvoice;
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

    public function expectsGetSepaTransferDate(SepaBatchId $batchId, DateTimeInterface $return): void
    {
        $this->mock
            ->expects('getSepaTransferDate')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }

    /** @param list<SepaExportInvoice> $return */
    public function expectsGetInvoicesForExport(SepaBatchId $batchId, array $return): void
    {
        $this->mock
            ->expects('getInvoicesForExport')
            ->with(equalTo($batchId))
            ->andReturn($return);
    }
}
