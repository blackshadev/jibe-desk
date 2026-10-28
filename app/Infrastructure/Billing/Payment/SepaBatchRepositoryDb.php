<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\MandateSequenceResolver;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\IbanFormat;
use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SepaBatchEmailData;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchRepository;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SepaExportInvoice;
use App\Domain\Billing\Payment\SepaExportInvoiceList;
use App\Domain\Billing\Payment\SepaExportPurchaseOrder;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
use App\Domain\SharedKernel\CompoundPrice;
use App\Infrastructure\Billing\Invoices\CreditInvoiceSettlementDb;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SepaBatch;
use App\Models\SepaExportOverride;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * @phpstan-type ExportInvoiceRow object{
 *     invoice_id: int,
 *     invoice_number: string,
 *     member_id: int,
 *     recipient_name: string,
 *     payment_information_id: int,
 *     iban: string,
 *     bic: string,
 *     mandate_date: string,
 *     last_collected_at: null|string,
 *     revoked_at: null|string,
 *     sepa_sequence_type: null|string,
 *     total_price: numeric-string,
 *     total_vat: numeric-string,
 * }
 * @phpstan-type CollectionCandidateRow object{
 *     invoice_id: int,
 *     invoice_number: string,
 *     recipient_name: string,
 *     member_id: null|int,
 *     status: string,
 *     sepa_sequence_type: null|string,
 *     last_collected_at: null|string,
 *     total_price: numeric-string,
 *     total_vat: numeric-string,
 * }
 */
final readonly class SepaBatchRepositoryDb implements SepaBatchRepository
{
    public function __construct(
        private MandateSequenceResolver $sequenceResolver,
        private CreditInvoiceSettlementDb $creditInvoiceSettlement,
    ) {}

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
        $from = $month->subMonth()->startOfMonth();
        $to = $month->endOfMonth();

        $transferDebitInvoiceIds = Invoice::query()
            ->join('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->join('members', 'members.id', '=', 'invoices.member_id')
            ->where('members.payment_method', PaymentMethod::Transfer->value)
            ->whereNull('invoices.sepa_batch_id')
            ->whereIn('invoices.status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->whereBetween('invoices.date', [$from, $to])
            ->groupBy('invoices.id')
            ->havingRaw('SUM(invoice_lines.price * invoice_lines.quantity) > 0')
            ->pluck('invoices.id')
            ->all();

        return Invoice::query()
            ->whereNull('sepa_batch_id')
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->whereBetween('date', [$from, $to])
            ->when(
                $transferDebitInvoiceIds !== [],
                static fn (Builder $query): Builder => $query->whereNotIn('id', $transferDebitInvoiceIds),
            )
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

    #[Override]
    public function getInvoicesForExport(SepaBatchId $batchId): SepaExportInvoiceList
    {
        /** @var Collection<int, ExportInvoiceRow> $rows */
        $rows = Invoice::query()
            ->where('invoices.sepa_batch_id', $batchId->value)
            ->where('invoices.status', InvoiceStatus::Pending)
            ->joinRelationship('lines')
            ->joinRelationship('member.currentPaymentInformation')
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoices.member_id',
                'invoices.recipient_name',
                'invoices.sepa_sequence_type',
                'payment_information.id as payment_information_id',
                'payment_information.banking_account_number as iban',
                'payment_information.banking_bic as bic',
                'payment_information.mandate_accepted_date as mandate_date',
                'payment_information.last_collected_at',
                'payment_information.revoked_at',
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
            )
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.member_id',
                'invoices.recipient_name',
                'invoices.sepa_sequence_type',
                'payment_information.id',
                'payment_information.banking_account_number',
                'payment_information.banking_bic',
                'payment_information.mandate_accepted_date',
                'payment_information.last_collected_at',
                'payment_information.revoked_at',
            )
            ->toBase()
            ->get();

        return new SepaExportInvoiceList(
            $rows
                ->filter(self::isExportable(...))
                ->map($this->exportInvoiceFromRow(...))
                ->values()
                ->all(),
        );
    }

    /** @param ExportInvoiceRow $row */
    private static function isExportable(object $row): bool
    {
        if ((float) $row->total_price < 0) {
            return IbanFormat::isWellFormed($row->iban);
        }

        return $row->revoked_at === null;
    }

    /** @param ExportInvoiceRow $row */
    private function exportInvoiceFromRow(object $row): SepaExportInvoice
    {
        $lastCollectedAt = self::lastCollectedAtFromRow($row);
        $sequenceType = $this->sequenceResolver->resolve(
            lastCollectedAt: $lastCollectedAt,
            frozenSequence: SequenceType::optionallyFrom($row->sepa_sequence_type),
        );

        return new SepaExportInvoice(
            invoiceId: InvoiceId::create($row->invoice_id),
            invoiceNumber: $row->invoice_number,
            recipientName: $row->recipient_name,
            total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
            iban: $row->iban,
            bic: $row->bic,
            mandateId: self::mandateIdFromRow($row),
            paymentInformationId: PaymentInformationId::create($row->payment_information_id),
            mandateDate: CarbonImmutable::parse($row->mandate_date),
            lastCollectedAt: $lastCollectedAt,
            sequenceType: $sequenceType,
        );
    }

    /** @param ExportInvoiceRow $row */
    private static function mandateIdFromRow(object $row): MandateId
    {
        return new MandateId(
            MemberId::create($row->member_id),
            PaymentInformationId::create($row->payment_information_id),
        );
    }

    /** @param ExportInvoiceRow $row */
    private static function lastCollectedAtFromRow(object $row): ?CarbonImmutable
    {
        return $row->last_collected_at === null
            ? null
            : CarbonImmutable::parse($row->last_collected_at);
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
    public function settleCreditPairs(SepaBatchId $batchId): void
    {
        $pendingIds = Invoice::query()
            ->where('sepa_batch_id', $batchId->value)
            ->where('status', InvoiceStatus::Pending)
            ->pluck('id')
            ->map(InvoiceId::create(...))
            ->all();

        $this->creditInvoiceSettlement->settlePending(new InvoiceIdList($pendingIds));
    }

    #[Override]
    public function closeBatch(SepaBatchId $batchId): void
    {
        SepaBatch::query()
            ->where('id', $batchId->value)
            ->update([
                'status' => SepaBatchStatus::Pending,
                'closed_at' => now(),
            ]);
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

    /** @return list<SepaCollectionCandidate> */
    #[Override]
    public function getCollectionCandidates(SepaBatchId $batchId): array
    {
        /** @var Collection<int, CollectionCandidateRow> $rows */
        $rows = DB::table('invoices')
            ->leftJoin('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->leftJoin('payment_information', static function (JoinClause $join): void {
                $join->on('payment_information.member_id', '=', 'invoices.member_id')
                    ->whereNull('payment_information.revoked_at');
            })
            ->where('invoices.sepa_batch_id', $batchId->value)
            ->whereIn('invoices.status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.recipient_name',
                'invoices.member_id',
                'invoices.status',
                'invoices.sepa_sequence_type',
                'payment_information.last_collected_at',
            )
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoices.recipient_name',
                'invoices.member_id',
                'invoices.status',
                'invoices.sepa_sequence_type',
                'payment_information.last_collected_at',
                DB::raw('COALESCE(SUM(invoice_lines.price * invoice_lines.quantity), 0) as total_price'),
                DB::raw('COALESCE(SUM(invoice_lines.vat * invoice_lines.quantity), 0) as total_vat'),
            )
            ->orderBy('invoices.id')
            ->get();

        return $rows
            ->map(function (object $row): SepaCollectionCandidate {
                $lastCollectedAt = $row->last_collected_at === null
                    ? null
                    : CarbonImmutable::parse($row->last_collected_at);
                $sequenceType = $this->sequenceResolver->resolve(
                    lastCollectedAt: $lastCollectedAt,
                    frozenSequence: SequenceType::optionallyFrom($row->sepa_sequence_type),
                );

                return new SepaCollectionCandidate(
                    invoiceId: InvoiceId::create($row->invoice_id),
                    invoiceNumber: $row->invoice_number,
                    recipientName: $row->recipient_name,
                    memberId: $row->member_id === null ? null : MemberId::create($row->member_id),
                    total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
                    status: InvoiceStatus::from($row->status),
                    sequenceType: $sequenceType,
                );
            })
            ->all();
    }

    #[Override]
    public function freezeSequenceTypes(SepaBatchId $batchId): void
    {
        /** @var array<string, list<int>> $invoiceIdsBySequenceType */
        $invoiceIdsBySequenceType = [];

        foreach ($this->getCollectionCandidates($batchId) as $candidate) {
            if (!$candidate->isDebit()) {
                continue;
            }

            $invoiceIdsBySequenceType[$candidate->sequenceType->value][] = $candidate->invoiceId->value;
        }

        foreach ($invoiceIdsBySequenceType as $sequenceType => $invoiceIds) {
            Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->whereNull('sepa_sequence_type')
                ->update(['sepa_sequence_type' => $sequenceType]);
        }
    }

    #[Override]
    public function replanSequenceTypes(SepaBatchId $batchId): void
    {
        DB::transaction(function () use ($batchId): void {
            $batch = SepaBatch::query()
                ->whereKey($batchId->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $batch->status !== SepaBatchStatus::Pending
                || $batch->exported_at !== null
                || $batch->submitted_at !== null
            ) {
                throw new DomainException('Only a pending, unexported and unsubmitted batch can be re-planned.');
            }

            Invoice::query()
                ->where('sepa_batch_id', $batchId->value)
                ->update(['sepa_sequence_type' => null]);

            $this->freezeSequenceTypes($batchId);
        });
    }

    #[Override]
    public function getExportContext(SepaBatchId $batchId): SepaBatchExportContext
    {
        /** @var SepaBatch $batch */
        $batch = SepaBatch::findOrFail($batchId->value);

        return new SepaBatchExportContext(
            status: $batch->status,
            sepaTransferDate: $batch->sepa_transfer_date,
            closedAt: $batch->closed_at,
            submittedAt: $batch->submitted_at,
        );
    }

    #[Override]
    public function recordExport(
        SepaBatchId $batchId,
        ExportConfirmation $confirmation,
        ?int $exportedByUserId,
        DateTimeInterface $exportedAt,
    ): void {
        DB::transaction(static function () use ($batchId, $confirmation, $exportedByUserId, $exportedAt): void {
            foreach ($confirmation->overrides as $override) {
                SepaExportOverride::query()->create([
                    'sepa_batch_id' => $batchId->value,
                    'invoice_id' => $override->invoiceId->value,
                    'reason' => $override->reason,
                    'note' => $override->note,
                    'exported_at' => $exportedAt,
                    'exported_by' => $exportedByUserId,
                ]);
            }

            SepaBatch::query()
                ->whereKey($batchId->value)
                ->update(['exported_at' => $exportedAt]);
        });
    }

    #[Override]
    public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId, DateTimeInterface $submittedAt): void
    {
        SepaBatch::query()
            ->whereKey($batchId->value)
            ->whereNull('submitted_at')
            ->update([
                'submitted_at' => $submittedAt,
                'submitted_by' => $submittedByUserId,
            ]);
    }
}
