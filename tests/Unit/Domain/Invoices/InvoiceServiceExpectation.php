<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceService;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class InvoiceServiceExpectation
{
    private function __construct(
        public MockInterface&InvoiceService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceService::class));
    }

    public function expectsMarkAsPaid(InvoiceId $id): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($id));
    }
}
