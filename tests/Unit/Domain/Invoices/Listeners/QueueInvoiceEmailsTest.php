<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Listeners;

use App\Domain\Invoices\Events\InvoiceBatchClosed;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\Listeners\QueueInvoiceEmails;
use App\Domain\Jobs\JobBatch;
use App\Jobs\Invoices\SendInvoiceEmail;
use Override;
use Tests\Unit\Domain\Invoices\InvoiceBatchRepositoryExpectation;
use Tests\Unit\Domain\Jobs\JobDispatcherExpectation;
use Tests\UnitTestCase;

final class QueueInvoiceEmailsTest extends UnitTestCase
{
    private InvoiceBatchRepositoryExpectation $batchRepo;
    private JobDispatcherExpectation $dispatcher;
    private QueueInvoiceEmails $listener;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepo = InvoiceBatchRepositoryExpectation::create();
        $this->dispatcher = JobDispatcherExpectation::create();

        $this->listener = new QueueInvoiceEmails(
            $this->batchRepo->mock,
            $this->dispatcher->mock,
        );
    }

    public function test_it_dispatches_batch_for_pending_invoices(): void
    {
        $batchId = InvoiceBatchId::create(10);
        $invoiceIds = [InvoiceId::create(1), InvoiceId::create(2), InvoiceId::create(3)];

        $this->batchRepo->expectsGetPendingInvoicesForBatch($batchId, $invoiceIds);
        $this->dispatcher->expectsDispatch(
            new JobBatch(
                'invoice-emails-batch-10',
                array_map(static fn (InvoiceId $id) => new SendInvoiceEmail($id), $invoiceIds),
            ),
        );

        $this->listener->handle(new InvoiceBatchClosed($batchId));
    }

    public function test_it_does_not_dispatch_when_no_pending_invoices(): void
    {
        $batchId = InvoiceBatchId::create(10);

        $this->batchRepo->expectsGetPendingInvoicesForBatch($batchId, []);
        $this->dispatcher->expectsNoDispatch();

        $this->listener->handle(new InvoiceBatchClosed($batchId));
    }
}
