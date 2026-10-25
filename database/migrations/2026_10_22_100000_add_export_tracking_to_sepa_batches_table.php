<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('sepa_batches', static function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->timestamp('exported_at')->nullable()->after('closed_at');
            $table->timestamp('submitted_at')->nullable()->after('exported_at');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sepa_batches', static function (Blueprint $table): void {
            $table->dropForeign(['submitted_by']);
            $table->dropColumn(['closed_at', 'exported_at', 'submitted_at', 'submitted_by']);
        });
    }
};
