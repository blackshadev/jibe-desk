<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Household> */
final class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [];
    }
}
