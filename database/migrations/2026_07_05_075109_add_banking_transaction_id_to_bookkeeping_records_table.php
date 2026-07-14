<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->foreignId('banking_transaction_id')
                ->nullable()
                ->constrained('banking_transactions')
                ->nullOnDelete();
            $table->index('banking_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->dropForeign(['banking_transaction_id']);
            $table->dropColumn('banking_transaction_id');
        });
    }
};
