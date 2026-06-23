<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\CostCenter;
use App\Models\StorageSpaceLocation;
use Illuminate\Database\Seeder;

final class StorageSpaceLocationSeeder extends Seeder
{
    public function run(): void
    {
        $costcenter = CostCenter::query()->where('number', CostCenterNumber::StorageRental)->firstOrFail();
        $locations = ['Container 3', 'Container 4', 'Container 5'];

        foreach ($locations as $name) {
            $billableItem = BillableItem::create([
                'description' => "Opslagplek {$name}",
                'price' => 15,
                'vat' => 3.15,
                'bill_period' => BillPeriod::Quarterly,
                'cost_center_id' => $costcenter->id,
            ]);

            StorageSpaceLocation::create([
                'name' => $name,
                'billable_item_id' => $billableItem->id,
            ]);
        }
    }
}
