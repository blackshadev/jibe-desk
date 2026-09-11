<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<BankAccount>
 */
final class BankAccountFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'iban' => BankAccount::normalizeIban((string) fake()->iban('NL')),
            'bic' => 'ABNANL2A',
            'active' => true,
        ];
    }

    public function checking(): self
    {
        return $this->state(['name' => 'Betaalrekening']);
    }

    public function savings(): self
    {
        return $this->state(['name' => 'Spaarrekening']);
    }

    public function inactive(): self
    {
        return $this->state(['active' => false]);
    }
}
