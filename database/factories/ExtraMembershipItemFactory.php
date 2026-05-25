<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExtraMembershipItem;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ExtraMembershipItemFactory extends Factory
{
    protected $model = ExtraMembershipItem::class;

    public function definition(): array
    {
        return [
            'billable_item_id' => BillableItemFactory::new(),
            'code' => $this->faker->word(),
        ];
    }
}
