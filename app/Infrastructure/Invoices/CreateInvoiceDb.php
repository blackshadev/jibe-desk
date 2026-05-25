<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\CreateInvoice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceNumberGenerator;
use App\Domain\Invoices\NewInvoice;
use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

final class CreateInvoiceDb implements CreateInvoice
{
    public function __construct(private InvoiceNumberGenerator $invoiceNumberGenerator)
    {
    }

    public function create(NewInvoice $invoice): InvoiceId
    {
        DB::beginTransaction();

        $invoiceNumber = $this->invoiceNumberGenerator->generate();

        $member = Member::findOrFail($invoice->memberId->value);

        $model = Invoice::query()->create([
            'invoice_batch_id' => $invoice->batchId?->value,
            'recipient_name' => $member->name,
            'recipient_address' => 'TODO',
            'invoice_number' => $invoiceNumber,
            'member_id' => $invoice->memberId->value,
            'date' => $invoice->invoiceDate,
        ]);

        $model->lines()->createMany(
            array_map(
                static fn (BillableItem $item) => [
                    'description' => $item->description,
                    'price' => $item->price->price,
                    'vat' => $item->price->vat,
                    'quantity' => $item->quantity,
                    'billable_item_id' => $item->id->value,
                ],
                $invoice->items->items
            )
        );

        DB::commit();

        return new InvoiceId($model->id);
    }
}
