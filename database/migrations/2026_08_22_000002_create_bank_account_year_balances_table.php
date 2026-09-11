<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('bank_account_year_balances', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->year('year');
            $table->decimal('opening_amount', 10, 3);
            $table->timestamps();
            $table->unique(['bank_account_id', 'year']);
            $table->index(['bank_account_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_year_balances');
    }
};
