<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->string('payment_method')->nullable()->after('membership_id');
        });

        DB::table('members')->update(['payment_method' => 'direct_debit']);

        Schema::table('members', static function (Blueprint $table): void {
            $table->string('payment_method')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }
};
