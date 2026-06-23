<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CostCenter>
 */
final class CostCenterFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('####'),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
