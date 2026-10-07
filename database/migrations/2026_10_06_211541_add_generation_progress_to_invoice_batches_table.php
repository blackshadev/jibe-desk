<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('generation_finished_at')->nullable();
            $table->unsignedInteger('generation_expected_invoices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->dropColumn(['generation_started_at', 'generation_finished_at', 'generation_expected_invoices']);
        });
    }
};
