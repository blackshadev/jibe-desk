<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceTarget;
use App\Domain\Invoices\Jobs\FinishSepaBatchGeneration;
use App\Domain\Invoices\Jobs\GenerateInvoice;
use App\Domain\Invoices\Jobs\SendSepaBatchCreatedEmail;
use App\Domain\Invoices\SepaBatch;
use App\Domain\Invoices\SepaBatchGeneratorImpl;
use App\Domain\Invoices\SepaBatchId;
use App\Domain\Jobs\JobBatch;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use DateTimeImmutable;
use Override;
use Tests\Unit\Domain\Jobs\JobDispatcherExpectation;
use Tests\UnitTestCase;

final class SepaBatchGeneratorImplTest extends UnitTestCase
{
    private JobDispatcherExpectation $jobDispatcher;
    private BillableItemsViewRepositoryExpectation $billableItemsViewRepository;
    private SepaBatchServiceExpectation $batchService;
    private SepaBatchGeneratorImpl $subject;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->jobDispatcher = JobDispatcherExpectation::create();
        $this->billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $this->batchService = SepaBatchServiceExpectation::create();

        $this->subject = new SepaBatchGeneratorImpl(
            $this->jobDispatcher->mock,
            $this->billableItemsViewRepository->mock,
            $this->batchService->mock,
        );
    }

    public function test_it_generates_one_invoice_per_billable_member(): void
    {
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $sepaTransferDate = new DateTimeImmutable('2026-06-08');
        $batch = new SepaBatch($invoiceDate, $sepaTransferDate);
        $memberIds = new MemberIdList([MemberId::create(1), MemberId::create(2)]);
        $batchId = SepaBatchId::create(9);

        $this->batchService->expectsCreateBatch($invoiceDate, $sepaTransferDate, $batchId);
        $this->billableItemsViewRepository->expectsListBillableMembers($invoiceDate, $memberIds);
        $this->batchService->expectsAttachBatchMonth($batchId, 3);
        $this->batchService->expectsStartGeneration($batchId, 5);
        $this->jobDispatcher->expectsDispatch(
            new JobBatch(
                'sepa-batch-2026-05-25-9',
                [
                    new GenerateInvoice(
                        new InvoiceTarget(MemberId::create(1), $invoiceDate, $batchId),
                    ),
                    new GenerateInvoice(
                        new InvoiceTarget(MemberId::create(2), $invoiceDate, $batchId),
                    ),
                ],
            )
                ->after(new FinishSepaBatchGeneration($batchId))
                ->after(new SendSepaBatchCreatedEmail($batchId)),
        );

        static::assertSame($batchId, $this->subject->generate($batch));
    }

    public function test_it_does_not_generate_invoices_when_no_billable_members_exist(): void
    {
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $sepaTransferDate = new DateTimeImmutable('2026-06-08');
        $batch = new SepaBatch($invoiceDate, $sepaTransferDate);
        $batchId = SepaBatchId::create(9);

        $this->batchService->expectsCreateBatch($invoiceDate, $sepaTransferDate, $batchId);
        $this->batchService->expectsAttachBatchMonth($batchId, 3);
        $this->batchService->expectsStartGeneration($batchId, 3);
        $this->batchService->expectsFinishGeneration($batchId);

        $this->billableItemsViewRepository->expectsListBillableMembers($invoiceDate, new MemberIdList([]));
        $this->jobDispatcher->expectsNoDispatch();

        static::assertSame($batchId, $this->subject->generate($batch));
    }
}
