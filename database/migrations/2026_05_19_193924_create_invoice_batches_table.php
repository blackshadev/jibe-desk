<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('invoice_batches', static function (Blueprint $table): void {
            $table->id();
            $table->date('invoice_date');
            $table->timestamps();
        });

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->foreignId('invoice_batch_id')->nullable()->constrained('invoice_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropColumn(['invoice_batch_id']);
        });
        Schema::dropIfExists('invoice_batches');
    }
};
