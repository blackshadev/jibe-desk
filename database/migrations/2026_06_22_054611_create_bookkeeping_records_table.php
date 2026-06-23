<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookkeeping_records', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->year('year');
            $table->foreignId('cost_center_id')->constrained('cost_centers');
            $table->decimal('amount_price', 10, 3);
            $table->decimal('amount_vat', 10, 3);
            $table->string('description');
            $table->nullableMorphs('reference');

            $table->index(['year', 'cost_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookkeeping_records');
    }
};
