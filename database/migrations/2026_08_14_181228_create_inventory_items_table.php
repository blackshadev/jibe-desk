<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained('inventory_categories');
            $table->string('description');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->unsignedSmallInteger('write_off_period_years');
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
