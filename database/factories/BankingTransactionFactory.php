<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankingTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<BankingTransaction> */
final class BankingTransactionFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $amount = fake()->randomFloat(3, -5000, 5000);

        return [
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'amount' => $amount,
            'description' => fake()->sentence(),
            'banking_account_number' => 'NL' . fake()->randomNumber(8, true) . fake()->randomNumber(8, true),
            'import_hash' => fake()->sha256(),
            'status' => BankTransactionStatus::Open->value,
        ];
    }

    public function forAccount(string $accountNumber): self
    {
        return $this->state(['banking_account_number' => $accountNumber]);
    }

    public function completed(): self
    {
        return $this->state(['status' => BankTransactionStatus::Completed->value]);
    }
}
