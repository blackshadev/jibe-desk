<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table
                ->foreignId('member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('member_id');
        });
    }
};
