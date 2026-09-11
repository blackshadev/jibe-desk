<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('banking_transaction_links', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banking_transaction_id')->constrained('banking_transactions')->cascadeOnDelete();
            $table->foreignId('linked_transaction_id')->constrained('banking_transactions')->cascadeOnDelete();
            $table->string('link_type')->default('internal_transfer');
            $table->timestamps();
            $table->unique(['banking_transaction_id', 'linked_transaction_id', 'link_type']);
            $table->index(['banking_transaction_id', 'link_type']);
            $table->index(['linked_transaction_id', 'link_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_transaction_links');
    }
};
