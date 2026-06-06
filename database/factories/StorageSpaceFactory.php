<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StorageSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageSpace>
 */
final class StorageSpaceFactory extends Factory
{
    protected $model = StorageSpace::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'location' => $this->faker->randomElement([
                'Container 3',
                'Container 4',
                'Container 5',
            ]),
            'number' => $this->faker->numberBetween(1, 30),
        ];
    }
}
