<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('household_member', static function (Blueprint $table): void {
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['household_id', 'member_id']);
            $table->unique('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_member');
    }
};
