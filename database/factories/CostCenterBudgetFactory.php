<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CostCenterBudget>
 */
final class CostCenterBudgetFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'year' => now()->year,
            'cost_center_id' => CostCenter::factory(),
            'starting_amount' => fake()->randomFloat(2, 0, 10_000),
        ];
    }
}
