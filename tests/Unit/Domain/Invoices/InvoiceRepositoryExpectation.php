<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceRepository;
use Mockery;
use Mockery\MockInterface;

final readonly class InvoiceRepositoryExpectation
{
    private function __construct(public MockInterface&InvoiceRepository $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceRepository::class));
    }

    public function expectsGetLatestInvoiceNumber(string $number): void
    {
        $this->mock
            ->expects('getLatestInvoiceNumber')
            ->withNoArgs()
            ->andReturn($number);
    }
}
