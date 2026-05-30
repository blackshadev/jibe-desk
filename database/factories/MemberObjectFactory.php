<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberObject;
use App\Models\MemberObjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

final class MemberObjectFactory extends Factory
{
    protected $model = MemberObject::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'object_type_id' => MemberObjectType::factory(),
            'name' => $this->faker->word(),
            'start_date' => $this->faker->date(),
            'end_date' => null,
        ];
    }
}
