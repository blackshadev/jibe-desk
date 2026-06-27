<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Bookkeeping\CostCenterYearResult;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Override;

final class BookkeepingRecordDbRepository implements BookkeepingRecordRepository
{
    #[Override]
    public function createForBatch(InvoiceBatchId $batchId): void
    {
        $now = now();
        BookkeepingRecord::query()->insertUsing(
            ['year', 'cost_center_id', 'amount_price', 'amount_vat', 'description', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
            Invoice::query()
                ->where('invoices.invoice_batch_id', $batchId->value)
                ->where('invoices.status', InvoiceStatus::Pending)
                ->whereNotExists(static function ($query): void {
                    $query
                        ->from('bookkeeping_records')
                        ->whereColumn('bookkeeping_records.reference_id', 'invoices.id')
                        ->where('bookkeeping_records.reference_type', Invoice::class);
                })
                ->joinRelationship('lines')
                ->joinRelationship('invoiceBatch')
                ->groupBy(
                    'invoices.id',
                    'invoices.invoice_number',
                    'invoice_lines.cost_center_id',
                    'invoice_batches.invoice_date',
                )
                ->select(
                    DB::connection()->getConfig()['driver'] === 'pgsql'
                        ? DB::raw('EXTRACT(YEAR FROM invoice_batches.invoice_date) AS year')
                        : DB::raw('STRFTIME(\'%Y\', invoice_batches.invoice_date)'),
                    'invoice_lines.cost_center_id',
                    DB::raw('SUM(invoice_lines.price * invoice_lines.quantity)'),
                    DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity)'),
                    DB::raw("CONCAT('Invoice ', invoices.invoice_number)"),
                    DB::raw(DB::escape(Invoice::class)),
                    'invoices.id',
                    DB::raw(DB::escape($now->format('c'))),
                    DB::raw(DB::escape($now->format('c'))),
                ),
        );
    }

    #[Override]
    public function createForPurchaseOrder(PurchaseOrderId $id): void
    {
        $now = now();
        BookkeepingRecord::query()->insertUsing(
            ['year', 'cost_center_id', 'amount_price', 'amount_vat', 'description', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
            PurchaseOrder::query()
                ->where('purchase_orders.id', $id->value)
                ->whereIn('purchase_orders.status', [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Paid])
                ->whereNotExists(static function ($query): void {
                    $query
                        ->from('bookkeeping_records')
                        ->whereColumn('bookkeeping_records.reference_id', 'purchase_orders.id')
                        ->where('bookkeeping_records.reference_type', PurchaseOrder::class);
                })
                ->joinRelationship('lines')
                ->groupBy(
                    'purchase_orders.id',
                    'purchase_orders.description',
                    'purchase_orders.date',
                    'purchase_order_lines.cost_center_id',
                )
                ->select(
                    DB::connection()->getConfig()['driver'] === 'pgsql'
                        ? DB::raw('EXTRACT(YEAR FROM purchase_orders.date) AS year')
                        : DB::raw('STRFTIME(\'%Y\', purchase_orders.date)'),
                    'purchase_order_lines.cost_center_id',
                    DB::raw('SUM(-purchase_order_lines.price)'),
                    DB::raw('SUM(-purchase_order_lines.price_vat)'),
                    DB::raw("CONCAT('Purchase order ', purchase_orders.description)"),
                    DB::raw(DB::escape(PurchaseOrder::class)),
                    'purchase_orders.id',
                    DB::raw(DB::escape($now->format('c'))),
                    DB::raw(DB::escape($now->format('c'))),
                ),
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
