<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\StorageSpaceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StorageSpaceLocation>
 */
final class StorageSpaceLocationFactory extends Factory
{
    protected $model = StorageSpaceLocation::class;

    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()
                ->unique()
                ->randomElement([
                    'Container 3',
                    'Container 4',
                    'Container 5',
                ]),
            'billable_item_id' => BillableItem::factory()->state([
                'description' => 'Opslagplek',
                'bill_period' => BillPeriod::Annually,
            ]),
        ];
    }
}
