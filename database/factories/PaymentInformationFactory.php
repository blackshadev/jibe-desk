<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\PaymentInformation;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PaymentInformation> */
final class PaymentInformationFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'banking_account_number' => fake()->iban('NL'),
            'banking_bic' => fake()->swiftBicNumber(),
            'banking_account_holder_name' => fake()->name(),
            'mandate_accepted_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'revoked_at' => null,
            'last_collected_at' => null,
        ];
    }

    public function signedAt(DateTimeInterface $signedAt): self
    {
        // @mago-expect lint:prefer-static-closure
        return $this->state(fn (array $_attributes): array => [
            'mandate_accepted_date' => $signedAt,
        ]);
    }

    public function collectedAt(DateTimeInterface $collectedAt): self
    {
        // @mago-expect lint:prefer-static-closure
        return $this->state(fn (array $_attributes): array => [
            'last_collected_at' => $collectedAt,
        ]);
    }

    public function revoked(?DateTimeInterface $revokedAt = null): self
    {
        // @mago-expect lint:prefer-static-closure
        return $this->state(fn (array $_attributes): array => [
            'revoked_at' => $revokedAt ?? now(),
        ]);
    }
}
