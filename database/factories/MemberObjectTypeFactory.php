<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemberObjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

final class MemberObjectTypeFactory extends Factory
{
    protected $model = MemberObjectType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'billable_item_id' => null,
        ];
    }
}
