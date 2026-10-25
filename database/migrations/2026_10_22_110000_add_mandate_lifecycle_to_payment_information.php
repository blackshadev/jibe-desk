<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->dropUnique(['member_id']);
            $table->index('member_id');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->dropColumn(['revoked_at', 'last_collected_at']);
            $table->dropIndex(['member_id']);
            $table->unique('member_id');
        });
    }
};
