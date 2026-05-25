<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('billable_items', static function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('bill_period');
            $table->decimal('price', 10, 3);
            $table->decimal('vat', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_items');
    }
};
