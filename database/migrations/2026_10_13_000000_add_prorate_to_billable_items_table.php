<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->boolean('prorate')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->dropColumn('prorate');
        });
    }
};
