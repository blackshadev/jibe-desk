<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined;
use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceServiceImpl;
use App\Domain\Billing\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Billing\Invoices\SendInvoiceEmailData;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepositoryExpectation;
use Tests\Unit\Domain\Jobs\JobDispatcherExpectation;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class InvoiceServiceImplTest extends UnitTestCase
{
    private CreateInvoiceExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private JobDispatcherExpectation $jobDispatcher;
    private EventDispatcherExpectation $eventDispatcher;
    private InvoiceServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = CreateInvoiceExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();
        $this->jobDispatcher = JobDispatcherExpectation::create();
        $this->eventDispatcher = EventDispatcherExpectation::create();

        $this->service = new InvoiceServiceImpl(
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
            $this->jobDispatcher->mock,
            $this->eventDispatcher->mock,
        );
    }

    public function test_create_credit_delegates_to_repository(): void
    {
        $originalId = InvoiceId::create(1);
        $creditId = InvoiceId::create(2);

        $this->repo->expectsCreateCredit($originalId, $creditId);

        $result = $this->service->createCredit($originalId);

        static::assertSame($creditId, $result);
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = InvoiceId::create(1);
        $ids = new InvoiceIdList([$id]);

        $this->repo->expectsMarkAsPaid($ids);
        $this->bookkeepingRepo->expectsCreateForInvoice($ids);

        $this->service->markAsPaid($ids);
    }

    public function test_mark_as_declined_dispatches_event_for_each_transitioned_invoice(): void
    {
        $id1 = InvoiceId::create(1);
        $id2 = InvoiceId::create(2);
        $ids = new InvoiceIdList([$id1, $id2]);
        $return = new InvoiceIdList([$id1, $id2]);

        $this->repo->expectsMarkAsDeclined($ids, null, null, $return);
        $this->eventDispatcher->expectsDispatch(new InvoicePaymentDeclined($id1));
        $this->eventDispatcher->expectsDispatch(new InvoicePaymentDeclined($id2));

        $this->service->markAsDeclined($ids);
    }

    public function test_mark_as_declined_does_not_dispatch_when_nothing_transitioned(): void
    {
        $ids = InvoiceIdList::fromArray([1, 2]);

        $this->repo->expectsMarkAsDeclined($ids, null, null, InvoiceIdList::fromArray([]));
        $this->eventDispatcher->expectsNotToDispatch();

        $this->service->markAsDeclined($ids);
    }

    public function test_mark_as_declined_does_not_create_bookkeeping_records(): void
    {
        $id = InvoiceId::create(1);
        $ids = new InvoiceIdList([$id]);

        $this->repo->expectsMarkAsDeclined($ids, null, null, new InvoiceIdList([$id]));
        $this->bookkeepingRepo->mock->shouldNotHaveReceived('createForInvoice');
        $this->eventDispatcher->expectsDispatch(new InvoicePaymentDeclined($id));

        $this->service->markAsDeclined($ids);
    }

    public function test_reopen_delegates_to_repository(): void
    {
        $id = InvoiceId::create(1);
        $ids = new InvoiceIdList([$id]);

        $this->repo->expectsReopen($ids);

         $this->service->reopen($ids);
    }

    public function test_restore_declined_updates_only_declined_and_creates_bookkeeping_records(): void
    {
        $ids = InvoiceIdList::fromArray([1, 2]);

        $this->repo->expectsMarkAsPendingIfDeclined($ids);
        $this->bookkeepingRepo->expectsCreateForInvoice($ids);

        $this->service->restoreDeclined($ids);
    }

    public function test_mark_as_pending_delegates_to_repository_and_dispatches_job(): void
    {
        $id1 = InvoiceId::create(1);
        $id2 = InvoiceId::create(2);
        $ids = new InvoiceIdList([$id1, $id2]);

        $this->repo->expectsMarkAsPending($ids);
        $this->bookkeepingRepo->expectsCreateForInvoice($ids);
        $this->jobDispatcher->expectsDispatch(new SendInvoiceEmail(new SendInvoiceEmailData($id1)));
        $this->jobDispatcher->expectsDispatch(new SendInvoiceEmail(new SendInvoiceEmailData($id2)));

        $this->service->markAsPending($ids);
    }
}
