<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\SepaExportInvoice;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Override;

final class InvoiceBatchRepositoryDb implements InvoiceBatchRepository
{
    #[Override]
    public function create(DateTimeInterface $invoiceDate, InvoiceBatchStatus $status): InvoiceBatchId
    {
        $model = InvoiceBatch::query()->create([
            'invoice_date' => $invoiceDate,
            'status' => $status,
        ]);

        return InvoiceBatchId::create($model->id);
    }

    #[Override]
    public function addOpenInvoicesFromBatchMonth(InvoiceBatchId $batchId): void
    {
        $month = new CarbonImmutable(InvoiceBatch::findOrFail($batchId->value)->invoice_date);

        Invoice::query()
            ->whereNull('invoice_batch_id')
            ->where('status', InvoiceStatus::Open)
            ->whereBetween('date', [
                $month->subMonth()->startOfMonth(),
                $month->endOfMonth(),
            ])
            ->update(['invoice_batch_id' => $batchId->value]);
    }

    /** @return list<SepaExportInvoice> */
    #[Override]
    public function getInvoicesForExport(InvoiceBatchId $batchId): array
    {
        $invoices = Invoice::query()
            ->where('invoice_batch_id', $batchId->value)
            ->join('invoice_lines', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('payment_information', 'invoices.member_id', '=', 'payment_information.member_id')
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoices.recipient_name',
                'payment_information.banking_account_number as iban',
                'payment_information.banking_bic as bic',
                'payment_information.uuid as mandate_id',
                'payment_information.mandate_accepted_date as mandate_date',
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
            )
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.recipient_name',
                'payment_information.banking_account_number',
                'payment_information.banking_bic',
                'payment_information.uuid',
                'payment_information.mandate_accepted_date',
            )
            ->get();

        return $invoices->map(static fn (object $row) => new SepaExportInvoice(
            invoiceId: InvoiceId::create((int) $row->invoice_id),
            invoiceNumber: $row->invoice_number,
            recipientName: $row->recipient_name,
            total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
            iban: $row->iban,
            bic: $row->bic,
            mandateId: $row->mandate_id,
            mandateDate: CarbonImmutable::parse($row->mandate_date),
        ))->all();
    }

    #[Override]
    public function markInvoicesAsPending(InvoiceBatchId $batchId): void
    {
        Invoice::query()
            ->where('invoice_batch_id', $batchId->value)
            ->where('status', InvoiceStatus::Open)
            ->update(['status' => InvoiceStatus::Pending]);
    }

    #[Override]
    public function closeBatch(InvoiceBatchId $batchId): void
    {
        InvoiceBatch::query()
            ->where('id', $batchId->value)
            ->update(['status' => InvoiceBatchStatus::Pending]);
    }

    #[Override]
    public function completeBatch(InvoiceBatchId $batchId): void
    {
        $batch = InvoiceBatch::findOrFail($batchId->value);

        $nonCompletableCount = $batch
            ->invoices()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->count();

        if ($nonCompletableCount > 0) {
            throw new DomainException('Batch still has open or pending invoices.');
        }

        $batch->update(['status' => InvoiceBatchStatus::Completed]);
    }
}
