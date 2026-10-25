<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class MandateLifecycleBackfill
{
    public function __construct(
        private string $table = 'payment_information',
    ) {}

    public function backfillRevocations(): int
    {
        return DB::table($this->table)
            ->whereNotNull('deleted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => DB::raw('deleted_at')]);
    }

    public function backfillLastCollectedAt(): int
    {
        $updated = 0;

        $collections = DB::table('invoices')
            ->join('sepa_batches', 'sepa_batches.id', '=', 'invoices.sepa_batch_id')
            ->whereNotNull('invoices.sepa_batch_id')
            ->whereNull('invoices.credit_invoice_id')
            ->where(static function (Builder $query): void {
                $query->where('sepa_batches.status', 'completed')
                    ->orWhereNotNull('sepa_batches.exported_at');
            })
            ->whereExists(static function (Builder $query): void {
                $query->select(DB::raw('1'))
                    ->from('invoice_lines')
                    ->whereColumn('invoice_lines.invoice_id', 'invoices.id')
                    ->groupBy('invoice_lines.invoice_id')
                    ->havingRaw('SUM(invoice_lines.price * invoice_lines.quantity) > 0');
            })
            ->groupBy('invoices.member_id')
            ->select('invoices.member_id', DB::raw('MAX(sepa_batches.sepa_transfer_date) as collected_at'))
            ->get();

        foreach ($collections as $collection) {
            $updated += DB::table($this->table)
                ->where('member_id', $collection->member_id)
                ->whereNull('revoked_at')
                ->whereNull('last_collected_at')
                ->update(['last_collected_at' => $collection->collected_at]);
        }

        return $updated;
    }
}
