<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLine> */
final class InvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 5, 100);

        return [
            'description' => $this->faker->sentence(),
            'price' => $price,
            'quantity' => $this->faker->randomFloat(2, 1, 5),
            'vat' => $price * 0.21,
        ];
    }
}
