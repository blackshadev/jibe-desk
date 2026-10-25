<?php

declare(strict_types=1);

use App\Infrastructure\Billing\Payment\MandateLifecycleBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        $backfill = new MandateLifecycleBackfill();
        $backfill->backfillRevocations();
        $backfill->backfillLastCollectedAt();
    }

    public function down(): void
    {
        DB::table('payment_information')->update([
            'revoked_at' => null,
            'last_collected_at' => null,
        ]);
    }
};
