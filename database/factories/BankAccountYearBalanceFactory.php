<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankAccountYearBalance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<BankAccountYearBalance>
 */
final class BankAccountYearBalanceFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'year' => now()->year,
            'opening_amount' => fake()->randomFloat(3, 0, 50_000),
        ];
    }
}
