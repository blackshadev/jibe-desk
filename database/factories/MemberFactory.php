<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Members\Gender;
use App\Models\Activity;
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
            'address_street' => $this->faker->streetName(),
            'address_housenumber' => (string) $this->faker->numberBetween(1, 200),
            'address_housenumber_addition' => $this->faker->optional()->randomElement(['A', 'B', 'C', 'bis']),
            'address_city' => $this->faker->city(),
            'address_postalcode' => $this->faker->postcode(),
            'birthdate' => $this->faker->date(),
            'gender' => $this->faker->randomElement(Gender::cases()),
            'membership_id' => Membership::factory(),
            'is_volunteer' => $this->faker->boolean(),
        ];
    }

    /** @param iterable<Activity> $activities */
    public function withRandomActivity(iterable $activities): self
    {
        $activity = $this->faker->optional()->randomElement($activities);

        if (!$activity) {
            return $this;
        }

        return $this->afterCreating(function (Member $member) use ($activity) {
            $member->activities()->attach($activity);
        });
    }
}
