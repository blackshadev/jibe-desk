<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
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

    public function expectsMarkAsPaid(InvoiceIdList $ids): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsDeclined(
        InvoiceIdList $ids,
        ?BankTransactionId $reversalTransactionId = null,
        ?string $note = null,
    ): void {
        $this->mock
            ->expects('markAsDeclined')
            ->with(equalTo($ids), equalTo($reversalTransactionId), equalTo($note));
    }

    public function expectsRestoreDeclined(InvoiceIdList $ids): void
    {
        $this->mock->expects('restoreDeclined')->with(equalTo($ids));
    }

    public function expectsMarkAsPending(InvoiceIdList $ids): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($ids));
    }
}
