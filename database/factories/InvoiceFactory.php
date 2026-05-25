<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
final class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $year = date('Y');

        return [
            'date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'invoice_number' => $this->faker->unique()->numerify("I-{$year}######"),
            'recipient_name' => $this->faker->name(),
            'recipient_address' => $this->faker->address(),
        ];
    }

    public function forMember(Member $member): self
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $member->id,
            'recipient_name' => $member->name,
        ]);
    }

    public function randomCount(): self
    {
        $c = $this->faker->numberBetween(0, 5);

        return $this->count($c);
    }

    public function withLines(?int $count = null): self
    {
        $count ??= $this->faker->numberBetween(1, 5);

        return $this->has(InvoiceLine::factory()->count($count), 'lines');
    }
}
