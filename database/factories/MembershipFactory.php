<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'adult_billable_item_id' => static fn (array $state) => BillableItem::factory()->state([
                'description' => 'Lidmaatschap ' . $state['name'],
                'bill_period' => BillPeriod::Annually->value,
            ]),
            'kids_billable_item_id' => static fn (array $state) => BillableItem::factory()->state([
                'description' => 'Lidmaatschap Jeugd ' . $state['name'],
                'bill_period' => BillPeriod::Annually->value,
            ]),
        ];
    }
}
