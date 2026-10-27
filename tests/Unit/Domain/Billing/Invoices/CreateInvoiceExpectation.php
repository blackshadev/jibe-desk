<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Billing\Invoices\ApplyInvoiceLines;
use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceRepository;
use App\Domain\Billing\Invoices\NewInvoice;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
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

    public function expectsCreateCredit(InvoiceId $originalInvoiceId, InvoiceId $return): void
    {
        $this->mock
            ->expects('createCredit')
            ->with(equalTo($originalInvoiceId))
            ->andReturn($return);
    }

    public function expectsMarkAsPaid(InvoiceIdList $ids): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsDeclined(
        InvoiceIdList $ids,
        ?BankTransactionId $reversalTransactionId,
        ?string $note,
        InvoiceIdList $return,
    ): void {
        $this->mock
            ->expects('markAsDeclined')
            ->with(equalTo($ids), equalTo($reversalTransactionId), equalTo($note))
            ->andReturn($return);
    }

    public function expectsReopen(InvoiceIdList $ids): void
    {
        $this->mock
            ->expects('reopen')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsPendingIfDeclined(InvoiceIdList $ids): void
    {
        $this->mock
            ->expects('markAsPendingIfDeclined')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsPending(InvoiceIdList $ids, InvoiceIdList $return): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($ids))
            ->andReturn($return);
    }
}
