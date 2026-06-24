<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_center_budgets', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->year('year');
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->decimal('starting_amount', 10, 3)->default(0);

            $table->unique(['cost_center_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_budgets');
    }
};
