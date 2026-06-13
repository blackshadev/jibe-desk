<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemberObject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<MemberObject> */
final class MemberObjectTypeFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'billable_item_id' => null,
        ];
    }
}
