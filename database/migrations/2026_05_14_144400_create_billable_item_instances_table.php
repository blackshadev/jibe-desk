<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('billable_item_instances', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('billable_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('bill_cycle_in_months');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_item_instances');
    }
};
