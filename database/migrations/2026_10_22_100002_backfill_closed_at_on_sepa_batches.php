<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        // Best-effort reconstruction of the close moment for batches that were closed before the column existed.
        // without it, a legacy Pending batch has no mail moment and the notice period would be measured from now().
        DB::table('sepa_batches')
            ->whereIn('status', ['pending', 'completed'])
            ->whereNull('closed_at')
            ->update(['closed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // The close moment cannot be distinguished from a real close afterwards, so the backfill is not reverted.
    }
};
