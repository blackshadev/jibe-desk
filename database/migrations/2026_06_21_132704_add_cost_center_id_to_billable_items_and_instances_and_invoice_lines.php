<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers');
            $table->index('cost_center_id');
        });

        Schema::table('invoice_lines', static function (Blueprint $table): void {
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers');
            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', static function (Blueprint $table): void {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
    }
};
