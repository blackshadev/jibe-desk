<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->date('sepa_transfer_date')->nullable();
        });

        DB::table('invoice_batches')
            ->whereNull('sepa_transfer_date')
            ->update(['sepa_transfer_date' => DB::raw('invoice_date')]);

        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->date('sepa_transfer_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->dropColumn('sepa_transfer_date');
        });
    }
};
