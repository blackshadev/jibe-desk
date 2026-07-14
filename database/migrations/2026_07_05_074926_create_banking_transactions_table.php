<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('banking_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 10, 3);
            $table->string('description');
            $table->string('banking_account_number');
            $table->string('import_hash', 64)->unique();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_transactions');
    }
};
