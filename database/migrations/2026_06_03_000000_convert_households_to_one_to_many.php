<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add household_id to members
        Schema::table('members', static function (Blueprint $table): void {
            $table->foreignId('household_id')->nullable()->after('membership_id')->constrained('households')->nullOnDelete();
        });

        // Migrate existing pivot data
        if (Schema::hasTable('household_member')) {
            \DB::table('household_member')->orderBy('household_id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    \DB::table('members')
                        ->where('id', $row->member_id)
                        ->update(['household_id' => $row->household_id]);
                }
            });

            Schema::dropIfExists('household_member');
        }
    }

    public function down(): void
    {
        // Recreate pivot and migrate data back
        Schema::create('household_member', static function (Blueprint $table): void {
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['household_id', 'member_id']);
            $table->unique('member_id');
        });

        \DB::table('members')->whereNotNull('household_id')->chunk(100, function ($rows) {
            $inserts = [];
            foreach ($rows as $row) {
                $inserts[] = [
                    'household_id' => $row->household_id,
                    'member_id' => $row->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($inserts)) {
                \DB::table('household_member')->insert($inserts);
            }
        });

        Schema::table('members', static function (Blueprint $table): void {
            $table->dropForeign(['household_id']);
            $table->dropColumn('household_id');
        });
    }
};
