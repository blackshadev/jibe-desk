<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\MatchCriteria;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BankTransactionRepositoryExpectation
{
    private function __construct(
        public MockInterface&BankTransactionRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BankTransactionRepository::class));
    }

    public function expectsExistsByHashAlways(bool $return): void
    {
        $this->mock
            ->shouldReceive('existsByHash')
            ->andReturn($return);
    }

    public function expectsCreateAlways(BankTransactionId $return): void
    {
        $this->mock
            ->shouldReceive('create')
            ->andReturn($return);
    }

    public function expectsCreateNever(): void
    {
        $this->mock
            ->shouldReceive('create')
            ->never();
    }

    public function expectsAttachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        $this->mock
            ->expects('attachInvoice')
            ->with(equalTo($bankTransactionId), equalTo($invoiceId));
    }

    public function expectsAttachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        $this->mock
            ->expects('attachPurchaseOrder')
            ->with(equalTo($bankTransactionId), equalTo($purchaseOrderId));
    }

    public function expectsGetAttachedInvoiceIds(BankTransactionId $id, InvoiceIdList $return): void
    {
        $this->mock
            ->expects('getAttachedInvoiceIds')
            ->with(equalTo($id))
            ->andReturn($return);
    }

    public function expectsGetAttachedPurchaseOrderIds(BankTransactionId $id, PurchaseOrderIdList $return): void
    {
        $this->mock
            ->expects('getAttachedPurchaseOrderIds')
            ->with(equalTo($id))
            ->andReturn($return);
    }

    public function expectsComplete(BankTransactionId $id): void
    {
        $this->mock
            ->expects('complete')
            ->with(equalTo($id));
    }

    public function expectsGetUnresolvedIds(int $limit, BankTransactionIdList $return): void
    {
        $this->mock
            ->expects('getUnresolvedIds')
            ->with(equalTo($limit))
            ->andReturn($return);
    }

    /** @param array<int, MatchCriteria> $return */
    public function expectsGetMatchCriteriaForIds(BankTransactionIdList $ids, array $return): void
    {
        $this->mock
            ->expects('getMatchCriteriaForIds')
            ->with(equalTo($ids))
            ->andReturn($return);
    }

    public function expectsMarkAsResolved(BankTransactionId $id): void
    {
        $this->mock
            ->expects('markAsResolved')
            ->with(equalTo($id));
    }

    public function expectsMarkAsUnresolvable(BankTransactionId $id): void
    {
        $this->mock
            ->expects('markAsUnresolvable')
            ->with(equalTo($id));
    }

    public function expectsFindReversalMatch(MatchCriteria $criteria, ?BankTransactionId $return): void
    {
        $this->mock
            ->expects('findReversalMatch')
            ->with(equalTo($criteria))
            ->andReturn($return);
    }

    public function expectsLinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
    {
        $this->mock
            ->expects('linkReversal')
            ->with(equalTo($reversalId), equalTo($originalId));
    }

    public function expectsUnlinkReversal(BankTransactionId $reversalId): void
    {
        $this->mock
            ->expects('unlinkReversal')
            ->with(equalTo($reversalId));
    }
}
