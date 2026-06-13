<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StorageSpaceRental>
 */
final class StorageSpaceRentalFactory extends Factory
{
    protected $model = StorageSpaceRental::class;

    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'storage_space_id' => StorageSpace::factory(),
            'member_id' => Member::factory(),
            'start_date' => fake()->date(),
            'end_date' => null,
        ];
    }
}
