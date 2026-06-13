<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\Activity;
use App\Models\BillableItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

final class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'billable_item_id' => static fn (array $state) => BillableItem::factory()->state([
                'bill_period' => BillPeriod::Monthly,
                'description' => "Activiteit {$state['name']}",
            ]),
            'start_date' => fake()->dateTimeBetween('-1 year', '+6 months'),
            'end_date' => static fn (array $state) => fake()->optional()->dateTimeBetween($state['start_date'], '+1 year'),
        ];
    }
}
