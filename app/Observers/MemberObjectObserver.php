<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\InvoiceRepository;
use App\Domain\Members\MemberId;
use App\Models\MemberObject;
use Carbon\CarbonImmutable;

final readonly class MemberObjectObserver
{
    public function __construct(private InvoiceRepository $invoiceRepository)
    {
    }

    public function created(MemberObject $memberObject): void
    {
        if ($memberObject->memberObjectType->billableItem === null) {
            return;
        }

        $billableItem = $memberObject->memberObjectType->billableItem;

        $apply = new ApplyInvoiceLines(
            new CarbonImmutable(),
            MemberId::create($memberObject->member_id),
            new BillableItemList([
                $billableItem->toInvoiceBillableItem(),
            ]),
        );

        $invoice = $this->invoiceRepository->applyLines($apply);

        $memberObject->invoice_line_id = $invoice->lineIds[0]->value;
        $memberObject->save();
    }

    public function deleted(MemberObject $memberObject): void
    {
        if ($memberObject->invoice_line_id === null) {
            return;
        }

        $memberObject->invoiceLine->delete();
    }
}
