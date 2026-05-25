<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillableItem>
 */
final class BillableItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 5, 100);

        return [
            'description' => $this->faker->sentence(),
            'price' => $price,
            'vat' => $price * 0.21,
            'bill_period' => $this->faker->randomElement(BillPeriod::cases())->value,
        ];
    }
}
