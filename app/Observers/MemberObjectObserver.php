<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\InvoiceRepository;
use App\Domain\Members\MemberId;
use App\Models\MemberObject;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class MemberObjectObserver
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
    ) {}

    /** @throws RuntimeException */
    public function created(MemberObject $memberObject): void
    {
        if ($memberObject->memberObjectType->billableItem === null) {
            return;
        }

        /** @var BillableItem[] $billableItems */
        $billableItems = [$memberObject->memberObjectType->billableItem->toInvoiceBillableItem()];

        $apply = new ApplyInvoiceLines(
            MemberId::create($memberObject->member_id),
            new CarbonImmutable(),
            new BillableItemList($billableItems),
        );

        $invoice = $this->invoiceRepository->applyLines($apply);

        if ($invoice->lineIds[0]?->value === null) {
            throw new RuntimeException('Invoice line was not created for member object');
        }

        $memberObject->invoice_line_id = $invoice->lineIds[0]->value;
        $memberObject->save();
    }

    public function deleted(MemberObject $memberObject): void
    {
        if ($memberObject->invoice_line_id === null) {
            return;
        }

        $memberObject->invoiceLine?->delete();
    }
}
