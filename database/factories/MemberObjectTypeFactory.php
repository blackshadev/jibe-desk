<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemberObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberObject> */
final class MemberObjectTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'billable_item_id' => null,
        ];
    }
}
