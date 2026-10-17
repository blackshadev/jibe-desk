<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceMailData;
use App\Domain\Billing\Invoices\InvoiceMailRepository;
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

    public function expectsMarkInvoiceAsSent(InvoiceId $invoiceId): void
    {
        $this->mock
            ->expects('markInvoiceAsSent')
            ->with(equalTo($invoiceId));
    }
}
