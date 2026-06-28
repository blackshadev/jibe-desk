<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('description');
            $table->date('date');
            $table->string('status')->index()->default('open');
            $table->string('image_path')->nullable();
            $table->string('creditor_name')->nullable();
            $table->string('creditor_iban')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
