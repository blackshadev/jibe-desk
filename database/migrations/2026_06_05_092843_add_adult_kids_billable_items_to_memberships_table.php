<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('adult_billable_item_id')->constrained('billable_items');
            $table->foreignId('kids_billable_item_id')->constrained('billable_items');
            $table->dropForeign(['billable_item_id']);
            $table->dropColumn('billable_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('billable_item_id')->constrained('billable_items');
            $table->dropForeign(['adult_billable_item_id']);
            $table->dropForeign(['kids_billable_item_id']);
            $table->dropColumn('adult_billable_item_id');
            $table->dropColumn('kids_billable_item_id');
        });
    }
};
