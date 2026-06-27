<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PurchaseOrderLine> */
final class PurchaseOrderLineFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5, 500);

        return [
            'description' => fake()->sentence(),
            'price' => $price,
            'price_vat' => $price * 0.21,
            'cost_center_id' => CostCenter::factory(),
        ];
    }
}
