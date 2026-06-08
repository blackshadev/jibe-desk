<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('storage_space_locations', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('billable_item_id')->constrained('billable_items')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_space_locations');
    }
};
