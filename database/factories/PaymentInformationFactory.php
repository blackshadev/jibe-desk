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
            'member_id' => Member::factory(),
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'banking_account_holder_name' => $this->faker->name(),
            'mandate_accepted_date' => $this->faker->date(),
        ];
    }
}
