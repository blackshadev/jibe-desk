<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PurchaseOrder> */
final class PurchaseOrderFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'creditor_name' => fake()->company(),
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'status' => fake()->randomElement(PurchaseOrderStatus::cases()),
        ];
    }

    public function open(): self
    {
        return $this->state(['status' => PurchaseOrderStatus::Open]);
    }

    public function pending(): self
    {
        return $this->state(['status' => PurchaseOrderStatus::Pending]);
    }

    public function paid(): self
    {
        return $this->state(['status' => PurchaseOrderStatus::Paid]);
    }

    public function withLines(?int $count = null): self
    {
        $count ??= fake()->numberBetween(1, 5);

        return $this->has(PurchaseOrderLine::factory()->count($count), 'lines');
    }
}
