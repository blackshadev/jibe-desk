<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('banking_transaction_references', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banking_transaction_id')->constrained('banking_transactions')->cascadeOnDelete();
            $table->nullableMorphs('reference');
            $table->timestamps();

            $table->unique(
                ['banking_transaction_id', 'reference_type', 'reference_id'],
                'btr_unique',
            );
            $table->index('reference_type');
            $table->index('reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_transaction_references');
    }
};
