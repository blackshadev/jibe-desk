<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class InvoiceMailRepositoryExpectation
{
    private function __construct(
        public MockInterface&InvoiceMailRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceMailRepository::class));
    }

    public function expectsGetInvoiceMailData(InvoiceId $invoiceId, InvoiceMailData $return): void
    {
        $this->mock
            ->expects('getInvoiceMailData')
            ->with(equalTo($invoiceId))
            ->andReturn($return);
    }
}
