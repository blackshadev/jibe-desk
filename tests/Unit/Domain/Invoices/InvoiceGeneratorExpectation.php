<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\GenerateInvoice;
use App\Domain\Invoices\InvoiceGenerator;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class InvoiceGeneratorExpectation
{
    private function __construct(
        public MockInterface&InvoiceGenerator $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceGenerator::class));
    }

    public function expectsGenerate(GenerateInvoice $generateInvoice): void
    {
        $this->mock
            ->expects('generate')
            ->with(equalTo($generateInvoice));
    }
}
