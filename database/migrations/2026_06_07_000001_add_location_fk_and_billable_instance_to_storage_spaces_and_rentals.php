<?php

declare(strict_types=1);

use App\Models\StorageSpace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('storage_spaces', static function (Blueprint $table): void {
            $table->dropUnique(['location', 'number']);
            $table->dropColumn('location');

            $table->foreignId('storage_space_location_id')
                ->constrained('storage_space_locations')
                ->cascadeOnDelete();

            $table->unique(['storage_space_location_id', 'number']);
        });

        Schema::table('storage_space_rentals', static function (Blueprint $table): void {
            $table->foreignId('billable_item_instance_id')
                ->nullable()
                ->after('member_id')
                ->constrained('billable_item_instances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storage_spaces', static function (Blueprint $table): void {
            $table->dropUnique(['storage_space_location_id', 'number']);
            $table->string('location')->nullable()->after('id');
            $table->unique(['location', 'number']);
        });

        StorageSpace::query()
            ->join('storage_space_locations', 'storage_spaces.storage_space_location_id', '=', 'storage_space_locations.id')
            ->update(['storage_spaces.location' => 'storage_space_locations.name']);

        Schema::table('storage_spaces', static function (Blueprint $table): void {
            $table->string('location')->nullable(false)->change();
            $table->dropColumn('storage_space_location_id');
        });

        Schema::table('storage_space_rentals', static function (Blueprint $table): void {
            $table->dropColumn('billable_item_instance_id');
        });
    }
};
