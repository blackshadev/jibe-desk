<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceLineId;
use App\Domain\Invoices\InvoiceNumberGenerator;
use App\Domain\Invoices\InvoiceRepository;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\NewInvoice;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

final class InvoiceRepositoryDb implements InvoiceRepository
{
    public function __construct(
        private InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {}

    #[Override]
    public function create(NewInvoice $invoice): InvoiceId
    {
        DB::beginTransaction();

        $invoiceNumber = $this->invoiceNumberGenerator->generate();

        $member = Member::findOrFail($invoice->memberId->value);

        $model = Invoice::query()->create([
            'invoice_batch_id' => $invoice->batchId?->value,
            'recipient_email' => $member->email,
            'recipient_name' => $member->name,
            'recipient_address' => $member->address,
            'invoice_number' => $invoiceNumber,
            'member_id' => $invoice->memberId->value,
            'date' => $invoice->invoiceDate,
        ]);

        $model
            ->lines()
            ->createMany(
                array_map(
                    static fn (BillableItem $item) => [
                        'description' => $item->description,
                        'price' => $item->price->price,
                        'vat' => $item->price->vat,
                        'quantity' => $item->quantity,
                        'billable_item_id' => $item->id->value,
                        'cost_center_id' => $item->costCenterId->value,
                    ],
                    $invoice->items->items,
                ),
            );

        DB::commit();

        return new InvoiceId($model->id);
    }

    #[Override]
    public function applyLines(ApplyInvoiceLines $invoice): AppliedInvoiceWithLineIds
    {
        DB::beginTransaction();

        $invoiceNumber = $this->invoiceNumberGenerator->generate();

        $member = Member::findOrFail($invoice->memberId->value);

        $date = CarbonImmutable::create($invoice->date);
        $model = Invoice::query()
            ->whereBetween(
                'date',
                [
                    $date->startOfMonth(),
                    $date->endOfMonth(),
                ],
            )
            ->firstOrCreate(
                [
                    'status' => InvoiceStatus::Open,
                    'member_id' => $invoice->memberId->value,
                ],
                [
                    'recipient_email' => $member->email,
                    'recipient_name' => $member->name,
                    'recipient_address' => $member->address,
                    'invoice_number' => $invoiceNumber,
                    'date' => $invoice->date,
                ],
            );

        /** @var Collection<InvoiceLine> $lines */
        $lines = $model
            ->lines()
            ->createMany(
                array_map(
                    static fn (BillableItem $item) => [
                        'description' => $item->description,
                        'price' => $item->price->price,
                        'vat' => $item->price->vat,
                        'quantity' => $item->quantity,
                        'billable_item_id' => $item->id->value,
                        'cost_center_id' => $item->costCenterId->value,
                    ],
                    $invoice->items->items,
                ),
            );

        DB::commit();

        return new AppliedInvoiceWithLineIds(
            isNew: $model->wasRecentlyCreated,
            invoiceId: InvoiceId::create($model->id),
            lineIds: array_map(static fn (InvoiceLine $item) => InvoiceLineId::create($item->id), $lines->all()),
        );
    }

    #[Override]
    public function markAsPaid(InvoiceIdList $ids): void
    {
        Invoice::query()
            ->whereIn('id', array_map(static fn (InvoiceId $id) => $id->value, $ids->ids))
            ->update(['status' => InvoiceStatus::Paid]);
    }
}
