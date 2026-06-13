<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\PaymentInformation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PaymentInformation> */
final class PaymentInformationFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'uuid' => fake()->unique->uuid(),
            'member_id' => Member::factory(),
            'banking_account_number' => fake()->iban('NL'),
            'banking_bic' => fake()->swiftBicNumber(),
            'banking_account_holder_name' => fake()->name(),
            'mandate_accepted_date' => fake()->date(),
        ];
    }
}
