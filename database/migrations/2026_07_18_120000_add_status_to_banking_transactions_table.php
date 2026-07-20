<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->string('status')->default('open')->after('import_hash');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
