<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('sepa_export_overrides', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sepa_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->text('note');
            $table->timestamp('exported_at');
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sepa_batch_id', 'exported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sepa_export_overrides');
    }
};
