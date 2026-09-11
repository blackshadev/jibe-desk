<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->restrictOnDelete();
            $table->foreignId('bank_statement_id')
                ->nullable()
                ->constrained('bank_statements')
                ->restrictOnDelete();
            $table->index(['bank_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->dropIndex(['bank_account_id', 'date']);
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('bank_statement_id');
        });
    }
};
