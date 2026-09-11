<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankAccount;
use App\Models\BankingTransaction;
use App\Models\BankStatement;
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

    public function forBankAccount(BankAccount $account): self
    {
        return $this->state(['bank_account_id' => $account->id]);
    }

    public function forStatement(BankStatement $statement): self
    {
        return $this->state([
            'bank_account_id' => $statement->bank_account_id,
            'bank_statement_id' => $statement->id,
        ]);
    }

    public function completed(): self
    {
        return $this->state(['status' => BankTransactionStatus::Completed->value]);
    }

    public function reversedBy(BankingTransaction $original): self
    {
        return $this->state([
            'reversed_by_transaction_id' => $original->id,
            'amount' => -$original->amount,
            'banking_account_number' => $original->banking_account_number,
        ]);
    }
}
