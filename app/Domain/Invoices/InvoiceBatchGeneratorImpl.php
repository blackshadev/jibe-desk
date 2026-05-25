<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemsViewRepository;

final readonly class InvoiceBatchGeneratorImpl implements InvoiceBatchGenerator
{
    public function __construct(
        private InvoiceGenerator $invoiceGenerator,
        private BillableItemsViewRepository $billableItemRepository,
    ) {
    }

    public function generate(InvoiceBatch $invoiceBatch): void
    {
        $billableMembers = $this->billableItemRepository->listBillableMembers($invoiceBatch->invoiceDate);

        foreach ($billableMembers->ids as $billableMember) {
            $this->invoiceGenerator->generate(new GenerateInvoice(
                memberId: $billableMember,
                invoiceDate: $invoiceBatch->invoiceDate,
                invoiceBatchId: $invoiceBatch->id,
            ));
        }
    }
}
