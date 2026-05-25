<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Members\Gender;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
final class MemberFactory extends Factory
{
    public function definition(): array
    {
        $nameGender = $this->faker->randomElement(['male', 'female']);
        $firstName = $this->faker->firstName($nameGender);

        return [
            'initials' => $firstName[0],
            'first_name' => $firstName,
            'infix_name' => $this->faker->randomElement(['van', 'de', 'den', 'van de', 'van den', '']),
            'last_name' => $this->faker->lastName(),
            'birthdate' => $this->faker->date(),
            'gender' => $this->faker->randomElement(Gender::cases()),
            'membership_id' => Membership::factory(),
        ];
    }
}
