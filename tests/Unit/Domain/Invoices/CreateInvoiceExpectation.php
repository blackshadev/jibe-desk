<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceRepository;
use App\Domain\Invoices\NewInvoice;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class CreateInvoiceExpectation
{
    private function __construct(
        public MockInterface&InvoiceRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceRepository::class));
    }

    public function expectsCreate(NewInvoice $invoice, InvoiceId $return): void
    {
        $this->mock
            ->expects('create')
            ->with(equalTo($invoice))
            ->andReturn($return);
    }

    public function expectsApplyLines(ApplyInvoiceLines $invoiceLines, AppliedInvoiceWithLineIds $return): void
    {
        $this->mock
            ->expects('applyLines')
            ->with(equalTo($invoiceLines))
            ->andReturn($return);
    }

    public function expectsMarkAsPaid(InvoiceId $id): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($id));
    }
}
