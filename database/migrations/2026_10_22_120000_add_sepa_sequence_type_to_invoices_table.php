<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->string('sepa_sequence_type', 4)->nullable()->after('sepa_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropColumn('sepa_sequence_type');
        });
    }
};
