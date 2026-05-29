<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('activity_member', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billable_item_instance_id')->nullable()->constrained('billable_item_instances')->nullOnDelete();
            $table->timestamps();

            $table->unique(['activity_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_member');
    }
};
