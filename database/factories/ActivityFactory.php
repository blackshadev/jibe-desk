<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\Activity;
use App\Models\BillableItem;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'billable_item_id' => function (array $state) {
                return BillableItem::factory()->state([
                    'bill_period' => BillPeriod::Monthly,
                    'description' => "Activiteit {$state['name']}",
                ]);
            },
            'start_date' => $this->faker->dateTimeBetween('-1 year', '+6 months'),
            'end_date' => function (array $state) {
                return $this->faker->optional()->dateTimeBetween($state['start_date'], '+1 year');
            },
        ];
    }
}
