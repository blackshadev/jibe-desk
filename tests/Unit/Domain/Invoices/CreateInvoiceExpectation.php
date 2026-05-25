<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\CreateInvoice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\NewInvoice;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class CreateInvoiceExpectation
{
    private function __construct(public MockInterface&CreateInvoice $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(CreateInvoice::class));
    }

    public function expectsCreate(NewInvoice $invoice, InvoiceId $return): void
    {
        $this->mock
            ->expects('create')
            ->with(equalTo($invoice))
            ->andReturn($return);
    }
}
