<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('bank_statements', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->string('statement_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('opening_balance', 10, 3);
            $table->decimal('closing_balance', 10, 3);
            $table->string('currency', 3)->default('EUR');
            $table->string('file_path');
            $table->string('integrity_status')->default('valid');
            $table->string('chain_status')->default('baseline');
            $table->decimal('balance_difference', 10, 3)->default(0);
            $table->timestamps();
            $table->unique(['bank_account_id', 'statement_number']);
            $table->index(['bank_account_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
