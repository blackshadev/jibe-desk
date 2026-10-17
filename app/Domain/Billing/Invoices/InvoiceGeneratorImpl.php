<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Billing\Subscriptions\BillableItemsViewRepository;
use Override;
use App\Domain\Billing\Payment\SepaBatchRepository;

final readonly class InvoiceGeneratorImpl implements InvoiceGenerator
{
    public function __construct(
        private BillableItemsViewRepository $billableViewRepository,
        private InvoiceRepository $invoiceRepository,
        private SepaBatchRepository $sepaBatchRepository,
    ) {}

    #[Override]
    public function generate(InvoiceTarget $createInvoice): ?InvoiceId
    {
        $billableItems = $this->billableViewRepository->listBillableItemsForMember(
            when: $createInvoice->invoiceDate,
            memberId: $createInvoice->memberId,
        );

        if ($billableItems->items === []) {
            return null;
        }

        $command = new ApplyInvoiceLines(
            $createInvoice->memberId,
            $createInvoice->invoiceDate,
            $billableItems,
        );

        $id = $this->invoiceRepository->applyLines($command)->invoiceId;

        if ($createInvoice->batchId) {
            $this->sepaBatchRepository->attachInvoice($createInvoice->batchId, $id);
        }

        return $id;
    }
}
