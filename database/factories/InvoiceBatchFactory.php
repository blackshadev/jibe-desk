<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Override;

/** @extends Factory<InvoiceBatch> */
final class InvoiceBatchFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'invoice_date' => fake()->date(),
            'created_at' => fake()->dateTime(),
            'updated_at' => Carbon::now(),
        ];
    }
}
