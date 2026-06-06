<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('storage_spaces', static function (Blueprint $table): void {
            $table->id();
            $table->string('location');
            $table->text('number');
            $table->timestamps();

            $table->unique(['location', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_spaces');
    }
};
