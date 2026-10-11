<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('stopped_at');
        });

        DB::table('members')
            ->whereNull('accepted_at')
            ->update(['accepted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->dropColumn('accepted_at');
        });
    }
};
