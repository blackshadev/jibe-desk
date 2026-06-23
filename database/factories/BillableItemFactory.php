<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<BillableItem>
 */
final class BillableItemFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5, 100);

        return [
            'description' => fake()->sentence(),
            'price' => $price,
            'vat' => $price * 0.21,
            'bill_period' => fake()->randomElement(BillPeriod::cases())->value,
            'cost_center_id' => CostCenter::factory(),
        ];
    }
}
