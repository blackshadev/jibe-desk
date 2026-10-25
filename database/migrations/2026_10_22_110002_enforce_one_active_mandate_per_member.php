<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX payment_information_one_active_mandate_per_member
             ON payment_information (member_id)
             WHERE revoked_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payment_information_one_active_mandate_per_member');

        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
