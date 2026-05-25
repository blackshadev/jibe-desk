<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemsViewRepository;

final readonly class InvoiceGeneratorImpl implements InvoiceGenerator
{
    public function __construct(
        private BillableItemsViewRepository $billableViewRepository,
        private CreateInvoice $invoiceRepository
    ) {
    }

    public function generate(GenerateInvoice $createInvoice): void
    {
        $billableItems = $this->billableViewRepository->listBillableItemsForMember(
            when: $createInvoice->invoiceDate,
            memberId: $createInvoice->memberId,
        );

        if (empty($billableItems->items)) {
            return;
        }

        $this->invoiceRepository->create(
            new NewInvoice(
                memberId: $createInvoice->memberId,
                invoiceDate: $createInvoice->invoiceDate,
                items: $billableItems,
                batchId: $createInvoice->invoiceBatchId,
            )
        );
    }
}
