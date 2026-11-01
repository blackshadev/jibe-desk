<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\CostCenterGroup;
use Database\Seeders\CostCenterNumber;
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
            'number' => $this->numberOutsideSeededRange(),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'cost_center_group_id' => CostCenterGroup::factory(),
        ];
    }

    /**
     * The seeders own the numbers listed in {@see CostCenterNumber}. Faker's unique()
     * only tracks the numbers it generated itself, so a random factory number would
     * every now and then collide with a seeded one on the unique index.
     */
    private function numberOutsideSeededRange(): string
    {
        do {
            $number = fake()->unique()->numerify('####');
        } while (CostCenterNumber::tryFrom((int) $number) !== null);

        return $number;
    }
}
