<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name')->index();
            $table->string('infix_name')->index();
            $table->string('last_name')->index();
            $table->string('email')->index();

            $table->string('gender');

            $table->date('birthdate');

            $table->foreignId('membership_id')
                ->index()
                ->constrained('memberships');

            $table->boolean('is_volunteer')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX members_user_id_unique ON members (user_id) WHERE user_id IS NOT NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
