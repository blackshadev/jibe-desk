<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->foreignId('reversed_by_transaction_id')
                ->nullable()
                ->constrained('banking_transactions')
                ->nullOnDelete();
            $table->boolean('is_credit_transaction')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by_transaction_id');
            $table->dropColumn('is_credit_transaction');
        });
    }
};
