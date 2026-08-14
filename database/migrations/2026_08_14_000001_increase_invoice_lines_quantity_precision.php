<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoice_lines', static function (Blueprint $table): void {
            $table->decimal('quantity', 10, 6)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', static function (Blueprint $table): void {
            $table->decimal('quantity', 10, 2)->default(1)->change();
        });
    }
};
