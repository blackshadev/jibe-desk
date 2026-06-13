<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberObject;
use App\Models\MemberObjectType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

final class MemberObjectFactory extends Factory
{
    protected $model = MemberObject::class;

    #[Override]
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'member_object_type_id' => MemberObjectType::factory(),
            'name' => fake()->word(),
            'start_date' => fake()->date(),
            'end_date' => null,
        ];
    }
}
