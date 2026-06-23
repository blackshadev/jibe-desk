<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<InvoiceLine> */
final class InvoiceLineFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5, 100);

        return [
            'description' => fake()->sentence(),
            'price' => $price,
            'quantity' => fake()->randomFloat(2, 1, 5),
            'vat' => $price * 0.21,
            'cost_center_id' => CostCenter::factory(),
        ];
    }
}
