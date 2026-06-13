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
use Override;

/** @extends Factory<Member> */
final class MemberFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $nameGender = fake()->randomElement(['male', 'female']);
        $firstName = fake()->firstName($nameGender);

        return [
            'first_name' => $firstName,
            'infix_name' => fake()->randomElement(['van', 'de', 'den', 'van de', 'van den', '']),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'address_street' => fake()->streetName(),
            'address_housenumber' => (string) fake()->numberBetween(1, 200),
            'address_housenumber_addition' => fake()->optional()->randomElement(['A', 'B', 'C', 'bis']),
            'address_city' => fake()->city(),
            'address_postalcode' => fake()->postcode(),
            'birthdate' => fake()->date(),
            'gender' => fake()->randomElement(Gender::cases()),
            'membership_id' => Membership::factory(),
            'is_volunteer' => fake()->boolean(),
            'created_at' => fake()->dateTimeBetween('-1 years', 'now'),
        ];
    }

    public function deleted(): self
    {
        // @mago-expect lint:prefer-static-closure
        return $this->state(fn (array $attributes) => [
            'deleted_at' => fake()->dateTimeBetween($attributes['created_at'], 'now'),
        ]);
    }

    public function withPaymentInfo(): self
    {
        return $this->has(PaymentInformation::factory());
    }

    /** @param iterable<Activity> $activities */
    public function withRandomActivity(iterable $activities): self
    {
        $activity = fake()->optional()->randomElement($activities);

        if (!$activity) {
            return $this;
        }

        return $this->afterCreating(static function (Member $member) use ($activity) {
            $member->activities()->attach($activity);
        });
    }

    public function inHousehold(Household $household): self
    {
        return $this->state(static fn (array $_attributes) => [
            'household_id' => $household->id,
        ]);
    }
}
