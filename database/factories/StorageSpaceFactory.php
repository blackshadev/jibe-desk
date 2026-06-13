<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StorageSpace>
 */
final class StorageSpaceFactory extends Factory
{
    protected $model = StorageSpace::class;

    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'storage_space_location_id' => StorageSpaceLocation::factory(),
            'number' => fake()->numberBetween(1, 30),
        ];
    }
}
