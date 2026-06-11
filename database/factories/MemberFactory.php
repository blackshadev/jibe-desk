<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Members\Gender;
use App\Models\Activity;
use App\Models\Household;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PaymentInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
final class MemberFactory extends Factory
{
    public function definition(): array
    {
        $nameGender = $this->faker->randomElement(['male', 'female']);
        $firstName = $this->faker->firstName($nameGender);

        return [
            'first_name' => $firstName,
            'infix_name' => $this->faker->randomElement(['van', 'de', 'den', 'van de', 'van den', '']),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker ->safeEmail(),
            'address_street' => $this->faker->streetName(),
            'address_housenumber' => (string) $this->faker->numberBetween(1, 200),
            'address_housenumber_addition' => $this->faker->optional()->randomElement(['A', 'B', 'C', 'bis']),
            'address_city' => $this->faker->city(),
            'address_postalcode' => $this->faker->postcode(),
            'birthdate' => $this->faker->date(),
            'gender' => $this->faker->randomElement(Gender::cases()),
            'membership_id' => Membership::factory(),
            'is_volunteer' => $this->faker->boolean(),
            'created_at' => $this->faker->dateTimeBetween('-1 years', 'now'),
        ];
    }

    public function deleted(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'deleted_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            ];
        });
    }

    public function withPaymentInfo(): self
    {
        return $this->has(PaymentInformation::factory());
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

    public function inHousehold(Household $household): self
    {
        return $this->state(fn (array $attributes) => [
            'household_id' => $household->id,
        ]);
    }
}
