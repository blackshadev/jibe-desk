<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<InvoiceBatch> */
final class InvoiceBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_date' => $this->faker->date(),
            'created_at' => $this->faker->dateTime(),
            'updated_at' => Carbon::now(),
        ];
    }
}
