<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('price', 10, 3);
            $table->decimal('price_vat', 10, 3);
            $table->foreignId('cost_center_id')->constrained('cost_centers');
            $table->timestamps();

            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
