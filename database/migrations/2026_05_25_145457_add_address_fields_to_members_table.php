<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('address_street');
            $table->string('address_housenumber');
            $table->string('address_housenumber_addition')->nullable();
            $table->string('address_city');
            $table->string('address_postalcode');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'address_street',
                'address_housenumber',
                'address_housenumber_addition',
                'address_city',
                'address_postalcode',
            ]);
        });
    }
};
