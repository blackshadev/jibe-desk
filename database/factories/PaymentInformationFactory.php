<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\PaymentInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentInformation> */
final class PaymentInformationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->unique->uuid(),
            'member_id' => Member::factory(),
            'banking_account_number' => $this->faker->iban('NL'),
            'banking_bic' => $this->faker->swiftBicNumber(),
            'banking_account_holder_name' => $this->faker->name(),
            'mandate_accepted_date' => $this->faker->date(),
        ];
    }
}
