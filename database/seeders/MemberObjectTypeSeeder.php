<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\MemberObjectType;
use Illuminate\Database\Seeder;

final class MemberObjectTypeSeeder extends Seeder
{
    public function run(): void
    {
        $billableItemFactory = BillableItem::factory()->state([
            'price' => 20.00,
            'vat' => 4.20,
            'bill_period' => BillPeriod::Once,
        ]);

        MemberObjectType::factory()
            ->for($billableItemFactory->state([
                'description' => 'Borg tag',
            ]))
            ->create(['name' => 'Tag']);

        MemberObjectType::factory()
            ->for($billableItemFactory->state([
                'description' => 'Borg sleutel',
            ]))
            ->create(['name' => 'Sleutel']);

        MemberObjectType::factory()->create(['name' => 'Anders']);
    }
}
