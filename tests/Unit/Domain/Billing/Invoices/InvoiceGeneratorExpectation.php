<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceGenerator;
use App\Domain\Billing\Invoices\InvoiceTarget;
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

    public function expectsGenerate(InvoiceTarget $generateInvoice): void
    {
        $this->mock
            ->expects('generate')
            ->with(equalTo($generateInvoice));
    }
}
