<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('invoice_declines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sepa_batch_id')->nullable()->constrained('sepa_batches')->nullOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->timestamp('declined_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'declined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_declines');
    }
};
