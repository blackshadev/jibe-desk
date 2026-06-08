<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ActivitySeeder::class);

        $this->call(MembershipSeeder::class);

        $this->call(MemberObjectTypeSeeder::class);

        $this->call(StorageSpaceLocationSeeder::class);

        if (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
