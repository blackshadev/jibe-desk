<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemsViewRepository;
use Override;

final readonly class InvoiceGeneratorImpl implements InvoiceGenerator
{
    public function __construct(
        private BillableItemsViewRepository $billableViewRepository,
        private InvoiceRepository $invoiceRepository,
    ) {}

    #[Override]
    public function generate(GenerateInvoice $createInvoice): ?InvoiceId
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

        return $this->invoiceRepository->applyLines($command)->invoiceId;
    }
}
