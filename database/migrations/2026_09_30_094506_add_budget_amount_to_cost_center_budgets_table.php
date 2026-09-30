<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_center_budgets', static function (Blueprint $table): void {
            $table->decimal('budget_amount', 10, 3)->default(0)->after('starting_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cost_center_budgets', static function (Blueprint $table): void {
            $table->dropColumn('budget_amount');
        });
    }
};
