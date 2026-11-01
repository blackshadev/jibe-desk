<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->string('lidnr')
                ->nullable()
                ->unique()
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->dropUnique(['lidnr']);
            $table->dropColumn('lidnr');
        });
    }
};
