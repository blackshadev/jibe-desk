<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<Invoice> */
final class InvoiceFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $year = date('Y');

        return [
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'invoice_number' => fake()->unique()->numerify("I-{$year}######"),
            'recipient_name' => fake()->name(),
            'recipient_address' => fake()->address(),
            'recipient_email' => fake()->safeEmail(),
            'status' => fake()->randomElement(InvoiceStatus::cases()),
        ];
    }

    public function forMember(Member $member): self
    {
        // @mago-expect lint:prefer-static-closure
        return $this->state(fn (array $_attributes) => [
            'member_id' => $member->id,
            'recipient_name' => $member->name,
            'recipient_address' => $member->address,
            'recipient_email' => $member->email,
        ]);
    }

    public function randomCount(): self
    {
        $c = fake()->numberBetween(0, 5);

        return $this->count($c);
    }

    public function withLines(?int $count = null): self
    {
        $count ??= fake()->numberBetween(1, 5);

        return $this->has(InvoiceLine::factory()->count($count), 'lines');
    }

    public function forBatch(InvoiceBatch $batch): self
    {
        return $this->state(['invoice_batch_id' => $batch->id]);
    }
}
