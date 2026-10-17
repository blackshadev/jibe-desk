<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->foreignId('sepa_batch_id')->nullable()->constrained('sepa_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sepa_batch_id');
        });
    }
};
