<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Activity;
use Illuminate\Database\Seeder;

final class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        Activity::factory()
            ->createMany([
                ['name' => 'Reguliere les volwassenen', 'start_date' => '2026-04-01', 'end_date' => '2026-10-30'],
                ['name' => 'Reguliere les kinderen', 'start_date' => '2026-04-01', 'end_date' => '2026-10-30'],
                ['name' => 'RTC 1', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30'],
                ['name' => 'RTC 2', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30'],
                ['name' => 'RTC 4', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30'],
            ]);
    }
}
