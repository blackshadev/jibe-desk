<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Bookkeeping\CostCenterYearResult;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Override;

final class BookkeepingRecordDbRepository implements BookkeepingRecordRepository
{
    #[Override]
    public function createForBatch(InvoiceBatchId $batchId): void
    {
        $rows = Invoice::query()
            ->where('invoices.invoice_batch_id', $batchId->value)
            ->where('invoices.status', InvoiceStatus::Pending)
            ->joinRelationship('lines')
            ->joinRelationship('invoiceBatch')
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoice_lines.cost_center_id',
                'invoice_batches.invoice_date',
            )
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoice_lines.cost_center_id',
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
                'invoice_batches.invoice_date',
            )
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('bookkeeping_records')->insert(
            $rows->map(static fn (object $row) => [
                'year' => Carbon::parse($row->invoice_date)->year,
                'cost_center_id' => $row->cost_center_id,
                'amount_price' => $row->total_price,
                'amount_vat' => $row->total_vat,
                'description' => 'Invoice ' . $row->invoice_number,
                'reference_type' => Invoice::class,
                'reference_id' => $row->invoice_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );
    }

    /** @return list<CostCenterYearResult> */
    #[Override]
    public function getResultsForYear(int $year): array
    {
        $rows = DB::table('cost_centers as cc')
            ->leftJoin('cost_center_budgets as cb', static function ($join) use ($year): void {
                $join->on('cb.cost_center_id', '=', 'cc.id')
                    ->where('cb.year', '=', $year);
            })
            ->leftJoin('bookkeeping_records as br', static function ($join) use ($year): void {
                $join->on('br.cost_center_id', '=', 'cc.id')
                    ->where('br.year', '=', $year);
            })
            ->groupBy('cc.id', 'cc.number', 'cc.title', 'cb.starting_amount')
            ->orderBy('cc.number')
            ->select(
                'cc.id',
                'cc.number',
                'cc.title',
                DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
                DB::raw('COALESCE(SUM(br.amount_price), 0) as total_price'),
                DB::raw('COALESCE(SUM(br.amount_vat), 0) as total_vat'),
            )
            ->get();

        return $rows->map(static fn (object $row) => new CostCenterYearResult(
            costCenterId: CostCenterId::create((int) $row->id),
            number: $row->number,
            title: $row->title,
            startingAmount: (float) $row->starting_amount,
            totalBookkeeping: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
        ))->all();
    }
}
