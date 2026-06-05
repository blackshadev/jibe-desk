<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'adult_billable_item_id' => function (array $state) {
                return BillableItem::factory()->state([
                    'description' => 'Lidmaatschap ' . $state['name'],
                    'bill_period' => BillPeriod::Annually->value,
                ]);
            },
            'kids_billable_item_id' => function (array $state) {
                return BillableItem::factory()->state([
                    'description' => 'Lidmaatschap Jeugd ' . $state['name'],
                    'bill_period' => BillPeriod::Annually->value,
                ]);
            },
        ];
    }
}
