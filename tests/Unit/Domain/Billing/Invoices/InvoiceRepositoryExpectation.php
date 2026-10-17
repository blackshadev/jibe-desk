<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceNumberRepository;
use Mockery;
use Mockery\MockInterface;

final readonly class InvoiceRepositoryExpectation
{
    private function __construct(
        public MockInterface&InvoiceNumberRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceNumberRepository::class));
    }

    public function expectsGetLatestInvoiceNumber(string $number): void
    {
        $this->mock
            ->expects('getLatestInvoiceNumber')
            ->withNoArgs()
            ->andReturn($number);
    }
}
