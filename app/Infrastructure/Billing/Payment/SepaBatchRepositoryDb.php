<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\SepaBatchEmailData;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchRepository;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SepaExportInvoice;
use App\Domain\Billing\Payment\SepaExportPurchaseOrder;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SepaBatch;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Override;

final class SepaBatchRepositoryDb implements SepaBatchRepository
{
    #[Override]
    public function create(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, SepaBatchStatus $status): SepaBatchId
    {
        $model = SepaBatch::query()->create([
            'invoice_date' => $invoiceDate,
            'sepa_transfer_date' => $sepaTransferDate,
            'status' => $status,
        ]);

        return SepaBatchId::create($model->id);
    }

    #[Override]
    public function addOpenInvoicesFromBatchMonth(SepaBatchId $batchId): int
    {
        $month = new CarbonImmutable(SepaBatch::findOrFail($batchId->value)->invoice_date);

        return Invoice::query()
            ->whereNull('sepa_batch_id')
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->whereBetween('date', [
                $month->subMonth()->startOfMonth(),
                $month->endOfMonth(),
            ])
            ->update(['sepa_batch_id' => $batchId->value]);
    }

    #[Override]
    public function addOpenPurchaseOrdersFromBatchMonth(SepaBatchId $batchId): int
    {
        $month = new CarbonImmutable(SepaBatch::findOrFail($batchId->value)->invoice_date);

        $ids = PurchaseOrder::query()
            ->whereNull('sepa_batch_id')
            ->where('approval_status', PurchaseOrderApprovalStatus::Accepted)
            ->where('status', PurchaseOrderStatus::Open)
            ->whereBetween('date', [
                $month->subMonth()->startOfMonth(),
                $month->endOfMonth(),
            ])
            ->pluck('id')
            ->all();

        PurchaseOrder::query()
            ->whereIn('id', $ids)
            ->update([
                'sepa_batch_id' => $batchId->value,
                'status' => PurchaseOrderStatus::Pending,
            ]);

        return count($ids);
    }

    #[Override]
    public function markGenerationStarted(SepaBatchId $batchId, int $expectedInvoices): void
    {
        SepaBatch::query()
            ->where('id', $batchId->value)
            ->update([
                'generation_started_at' => now(),
                'generation_expected_invoices' => $expectedInvoices,
            ]);
    }

    #[Override]
    public function markGenerationFinished(SepaBatchId $batchId): void
    {
        SepaBatch::query()
            ->where('id', $batchId->value)
            ->update(['generation_finished_at' => now()]);
    }

    /** @return list<SepaExportInvoice> */
    #[Override]
    public function getInvoicesForExport(SepaBatchId $batchId): array
    {
        $invoices = Invoice::query()
            ->where('sepa_batch_id', $batchId->value)
            ->joinRelationship('lines')
            ->joinRelationship('member.paymentInformation')
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'members.id as member_id',
                'payment_information.id as payment_information_id',
                'invoices.recipient_name',
                'payment_information.banking_account_number as iban',
                'payment_information.banking_bic as bic',
                'payment_information.mandate_accepted_date as mandate_date',
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
            )
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.recipient_name',
                'members.id',
                'payment_information.id',
                'payment_information.banking_account_number',
                'payment_information.banking_bic',
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
            mandateId: new MandateId(
                new MemberId((int) $row->member_id),
                new PaymentInformationId((int) $row->payment_information_id),
            ),
            mandateDate: CarbonImmutable::parse($row->mandate_date),
        ))->all();
    }

    /** @return list<SepaExportPurchaseOrder> */
    #[Override]
    public function getPurchaseOrdersForExport(SepaBatchId $batchId): array
    {
        return PurchaseOrder::query()
            ->where('sepa_batch_id', $batchId->value)
            ->where('status', PurchaseOrderStatus::Pending)
            ->with('lines')
            ->get()
            ->map(static fn (PurchaseOrder $purchaseOrder): SepaExportPurchaseOrder => new SepaExportPurchaseOrder(
                purchaseOrderId: PurchaseOrderId::create($purchaseOrder->id),
                creditorName: $purchaseOrder->creditor_name ?? '',
                creditorIban: $purchaseOrder->creditor_iban ?? '',
                total: $purchaseOrder->total,
            ))
            ->all();
    }

    #[Override]
    public function markInvoicesAsPending(SepaBatchId $batchId): void
    {
        $batch = SepaBatch::findOrFail($batchId->value);

        Invoice::query()
            ->where('sepa_batch_id', $batchId->value)
            ->where('status', InvoiceStatus::Open)
            ->update([
                'status' => InvoiceStatus::Pending,
                'date' => $batch->invoice_date,
            ]);
    }

    #[Override]
    public function closeBatch(SepaBatchId $batchId): void
    {
        SepaBatch::query()
            ->where('id', $batchId->value)
            ->update(['status' => SepaBatchStatus::Pending]);
    }

    #[Override]
    public function completeBatch(SepaBatchId $batchId): void
    {
        $batch = SepaBatch::findOrFail($batchId->value);

        $nonCompletableCount = $batch
            ->invoices()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->count();

        if ($nonCompletableCount > 0) {
            throw new DomainException('Batch still has open or pending invoices.');
        }

        $batch->update(['status' => SepaBatchStatus::Completed]);
    }

    /** @return list<InvoiceId> */
    #[Override]
    public function getPendingInvoicesForBatch(SepaBatchId $batchId): array
    {
        return Invoice::query()
            ->where('sepa_batch_id', $batchId->value)
            ->where('status', InvoiceStatus::Pending)
            ->pluck('id')
            ->map(InvoiceId::create(...))
            ->all();
    }

    #[Override]
    public function getSepaTransferDate(SepaBatchId $batchId): DateTimeInterface
    {
        /** @var SepaBatch $batch */
        $batch = SepaBatch::findOrFail($batchId->value);
        return $batch->sepa_transfer_date;
    }

    #[Override]
    public function getBatchEmailData(SepaBatchId $batchId): SepaBatchEmailData
    {
        $row = DB::table('sepa_batches')
            ->where('sepa_batches.id', $batchId->value)
            ->join('invoices', 'invoices.sepa_batch_id', '=', 'sepa_batches.id')
            ->join('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->select(
                'sepa_batches.invoice_date',
                DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
            )
            ->groupBy('sepa_batches.id', 'sepa_batches.invoice_date')
            ->first();

        if ($row === null) {
            $batch = SepaBatch::findOrFail($batchId->value);

            return new SepaBatchEmailData(
                id: $batchId,
                invoiceDate: $batch->invoice_date,
                invoiceCount: 0,
                total: CompoundPrice::empty(),
            );
        }

        return new SepaBatchEmailData(
            id: $batchId,
            invoiceDate: CarbonImmutable::parse($row->invoice_date),
            invoiceCount: (int) $row->invoice_count,
            total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
        );
    }

    #[Override]
    public function attachInvoice(SepaBatchId $batchId, InvoiceId $id): void
    {
        Invoice::query()
            ->findOrFail($id->value)
            ->update(['sepa_batch_id' => $batchId->value]);
    }

    #[Override]
    public function attachInvoices(SepaBatchId $batchId, InvoiceIdList $ids): int
    {
        $batch = SepaBatch::findOrFail($batchId->value);

        if ($batch->status !== SepaBatchStatus::Open) {
            throw new DomainException('Only an open batch can receive retried invoices.');
        }

        return Invoice::query()
            ->whereIn('id', $ids->values())
            ->whereNull('sepa_batch_id')
            ->where('status', InvoiceStatus::Open)
            ->update(['sepa_batch_id' => $batchId->value]);
    }
}
