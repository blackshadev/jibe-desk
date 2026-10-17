<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Billing\Subscriptions\BillableItemsViewRepository;
use App\Domain\Billing\Payment\Jobs\FinishSepaBatchGeneration;
use App\Domain\Billing\Invoices\Jobs\GenerateInvoice;
use App\Domain\Billing\Payment\Jobs\SendSepaBatchCreatedEmail;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobDispatcher;
use App\Domain\Members\MemberId;
use Override;
use Webmozart\Assert\Assert;
use App\Domain\Billing\Invoices\InvoiceTarget;

final readonly class SepaBatchGeneratorImpl implements SepaBatchGenerator
{
    public function __construct(
        private JobDispatcher $dispatcher,
        private BillableItemsViewRepository $billableItemRepository,
        private SepaBatchService $batchService,
    ) {}

    #[Override]
    public function generate(SepaBatch $sepaBatch): SepaBatchId
    {
        $batchId = $this->batchService->createBatch($sepaBatch->invoiceDate, $sepaBatch->sepaTransferDate);
        $attachedCount = $this->batchService->attachBatchMonthInvoices($batchId);

        $billableMembers = $this->billableItemRepository->listBillableMembers($sepaBatch->invoiceDate);

        $this->batchService->startGeneration($batchId, $attachedCount + count($billableMembers->ids));

        $batchName = sprintf('sepa-batch-%s-%s', $sepaBatch->invoiceDate->format('Y-m-d'), $batchId->value);

        if ($billableMembers->ids === []) {
            $this->batchService->finishGeneration($batchId);

            return $batchId;
        }

        $jobs = array_map(
            static fn (MemberId $id) => new GenerateInvoice(
                new InvoiceTarget(
                    memberId: $id,
                    invoiceDate: $sepaBatch->invoiceDate,
                    batchId: $batchId,
                ),
            ),
            $billableMembers->ids,
        );

        Assert::isList($jobs);
        $batch = new JobBatch($batchName, $jobs)
            ->after(new FinishSepaBatchGeneration($batchId))
            ->after(new SendSepaBatchCreatedEmail($batchId));

        $this->dispatcher->dispatch($batch);

        return $batchId;
    }
}
