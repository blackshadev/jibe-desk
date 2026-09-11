<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use App\Models\BankAccount;
use App\Models\BankStatement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<BankStatement>
 */
final class BankStatementFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', '-1 month');

        return [
            'bank_account_id' => BankAccount::factory(),
            'statement_number' => (string) fake()->randomNumber(3, true),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->modify('+1 week')->format('Y-m-d'),
            'opening_balance' => fake()->randomFloat(3, 0, 50_000),
            'closing_balance' => fake()->randomFloat(3, 0, 50_000),
            'currency' => 'EUR',
            'file_path' => 'mt940-imports/fake.mta',
            'integrity_status' => StatementIntegrityStatus::Valid->value,
            'chain_status' => StatementChainStatus::Baseline->value,
            'balance_difference' => 0,
        ];
    }

    public function mismatch(): self
    {
        return $this->state([
            'integrity_status' => StatementIntegrityStatus::Mismatch->value,
            'balance_difference' => 12.34,
        ]);
    }

    public function brokenChain(): self
    {
        return $this->state([
            'chain_status' => StatementChainStatus::Broken->value,
        ]);
    }
}
