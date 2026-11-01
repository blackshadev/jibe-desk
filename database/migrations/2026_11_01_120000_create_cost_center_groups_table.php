<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('cost_center_groups', static function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('external_number')->nullable();
            $table->string('name');
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('cost_center_groups')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_groups');
    }
};
