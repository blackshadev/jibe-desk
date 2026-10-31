<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

final class StorageSpaceSeeder extends Seeder
{
    public function run(): void
    {
        $location3 = StorageSpaceLocation::query()->where('name', 'Container 3')->firstOrFail();
        StorageSpace::factory()
            ->state([
                'storage_space_location_id' => $location3->id,
            ])
            ->sequence(static fn (Sequence $sequence) => ['number' => $sequence->index + 1])
            ->count(36)
            ->create();

        $location4 = StorageSpaceLocation::query()->where('name', 'Container 4')->firstOrFail();
        StorageSpace::factory()
            ->state([
                'storage_space_location_id' => $location4->id,
            ])
            ->sequence(static fn (Sequence $sequence) => ['number' => $sequence->index + 1])
            ->count(36)
            ->create();

        $location5 = StorageSpaceLocation::query()->where('name', 'Container 5')->firstOrFail();
        StorageSpace::factory()
            ->state([
                'storage_space_location_id' => $location5->id,
            ])
            ->sequence(static fn (Sequence $sequence) => ['number' => $sequence->index + 1])
            ->count(17)
            ->create();
    }
}
