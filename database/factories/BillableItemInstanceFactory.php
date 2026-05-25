<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BillableItemInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillableItemInstance> */
final class BillableItemInstanceFactory extends Factory
{
    protected $model = BillableItemInstance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'member_id' => null,
            'billable_item_id' => null,
            'start_date' => $this->faker->date(),
            'end_date' => null,
        ];
    }
}
