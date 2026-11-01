<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenterGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CostCenterGroup>
 */
final class CostCenterGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('#####'),
            'external_number' => fake()->unique()->numerify('##'),
            'name' => fake()->words(2, true),
            'parent_id' => null,
        ];
    }
}
