<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExtraMembershipItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

final class ExtraMembershipItemFactory extends Factory
{
    protected $model = ExtraMembershipItem::class;

    #[Override]
    public function definition(): array
    {
        return [
            'billable_item_id' => BillableItemFactory::new(),
            'code' => fake()->word(),
        ];
    }
}
