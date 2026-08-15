<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<InventoryItem>
 */
final class InventoryItemFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-5 years', 'now'),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'write_off_period_years' => fake()->numberBetween(1, 10),
        ];
    }
}
