<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('member_object_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('billable_item_id')->nullable()->constrained('billable_items')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_object_types');
    }
};
