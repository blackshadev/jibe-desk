<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceBatch;
use App\Domain\Invoices\InvoiceBatchGeneratorImpl;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceTarget;
use App\Domain\Jobs\JobBatch;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use App\Jobs\Invoices\GenerateInvoice;
use App\Jobs\Invoices\SendInvoiceBatchCreatedEmail;
use DateTimeImmutable;
use Tests\Unit\Domain\Jobs\JobDispatcherExpectation;
use Tests\UnitTestCase;

final class InvoiceBatchGeneratorImplTest extends UnitTestCase
{
    private JobDispatcherExpectation $jobDispatcher;
    private BillableItemsViewRepositoryExpectation $billableItemsViewRepository;
    private InvoiceBatchServiceExpectation $batchService;
    private InvoiceBatchGeneratorImpl $subject;

    protected function setup(): void
    {
        parent::setup();

        $this->jobDispatcher = JobDispatcherExpectation::create();
        $this->billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $this->batchService = InvoiceBatchServiceExpectation::create();

        $this->subject = new InvoiceBatchGeneratorImpl(
            $this->jobDispatcher->mock,
            $this->billableItemsViewRepository->mock,
            $this->batchService->mock,
        );
    }

    public function test_it_generates_one_invoice_per_billable_member(): void
    {
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $batch = new InvoiceBatch($invoiceDate);
        $memberIds = new MemberIdList([MemberId::create(1), MemberId::create(2)]);
        $batchId = InvoiceBatchId::create(9);

        $this->batchService->expectsCreateBatch($invoiceDate, $batchId);
        $this->billableItemsViewRepository->expectsListBillableMembers($invoiceDate, $memberIds);
        $this->batchService->expectsAttachBatchMonth($batchId);
        $this->jobDispatcher->expectsDispatch(
            new JobBatch(
                'invoice-batch-2026-05-25-9',
                [
                    new GenerateInvoice(
                        new InvoiceTarget(MemberId::create(1), $invoiceDate, $batchId),
                    ),
                    new GenerateInvoice(
                        new InvoiceTarget(MemberId::create(2), $invoiceDate, $batchId),
                    ),
                ],
            )->after(new SendInvoiceBatchCreatedEmail($batchId)),
        );

        $this->subject->generate($batch);
    }

    public function test_it_does_not_generate_invoices_when_no_billable_members_exist(): void
    {
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $batch = new InvoiceBatch($invoiceDate);
        $batchId = InvoiceBatchId::create(9);

        $this->batchService->expectsCreateBatch($invoiceDate, $batchId);
        $this->batchService->expectsAttachBatchMonth($batchId);

        $this->billableItemsViewRepository->expectsListBillableMembers($invoiceDate, new MemberIdList([]));
        $this->jobDispatcher->expectsNoDispatch();

        $this->subject->generate($batch);
    }
}
