<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('billable_item_instances', static function (Blueprint $table): void {
            $table->unsignedBigInteger('bill_cycle_in_months')->change();
        });
    }

    public function down(): void
    {
        Schema::table('billable_item_instances', static function (Blueprint $table): void {
            $table->unsignedTinyInteger('bill_cycle_in_months')->change();
        });
    }
};
