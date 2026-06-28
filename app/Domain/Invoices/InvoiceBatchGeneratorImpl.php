<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemsViewRepository;
use App\Domain\Invoices\Jobs\GenerateInvoice;
use App\Domain\Invoices\Jobs\SendInvoiceBatchCreatedEmail;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobDispatcher;
use App\Domain\Members\MemberId;
use Override;
use Webmozart\Assert\Assert;

final readonly class InvoiceBatchGeneratorImpl implements InvoiceBatchGenerator
{
    public function __construct(
        private JobDispatcher $dispatcher,
        private BillableItemsViewRepository $billableItemRepository,
        private InvoiceBatchService $batchService,
    ) {}

    #[Override]
    public function generate(InvoiceBatch $invoiceBatch): void
    {
        $batchId = $this->batchService->createBatch($invoiceBatch->invoiceDate);
        $this->batchService->attachBatchMonth($batchId);

        $billableMembers = $this->billableItemRepository->listBillableMembers($invoiceBatch->invoiceDate);

        $batchName = sprintf('invoice-batch-%s-%s', $invoiceBatch->invoiceDate->format('Y-m-d'), $batchId->value);

        if ($billableMembers->ids === []) {
            return;
        }

        $jobs = array_map(
            static fn (MemberId $id) => new GenerateInvoice(
                new InvoiceTarget(
                    memberId: $id,
                    invoiceDate: $invoiceBatch->invoiceDate,
                    batchId: $batchId,
                ),
            ),
            $billableMembers->ids,
        );
        Assert::isList($jobs);
        $batch = new JobBatch($batchName, $jobs)
            ->after(new SendInvoiceBatchCreatedEmail($batchId));

        $this->dispatcher->dispatch($batch);
    }
}
