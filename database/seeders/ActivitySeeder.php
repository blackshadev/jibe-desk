<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\Activity;
use App\Models\BillableItem;
use App\Models\CostCenter;
use Illuminate\Database\Seeder;

final class ActivitySeeder extends Seeder
{
    private const ITEMS = [
        ['name' => 'Reguliere les volwassenen', 'start_date' => '2026-04-01', 'end_date' => '2026-10-30', 'price' => 37, 'costcenter' => CostCenterNumber::Lessons],
        ['name' => 'Reguliere les kinderen', 'start_date' => '2026-04-01', 'end_date' => '2026-10-30', 'price' => 32, 'costcenter' => CostCenterNumber::Lessons],
        ['name' => 'RTC 1', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'price' => 1000, 'costcenter' => CostCenterNumber::Rtc],
        ['name' => 'RTC 2', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'price' => 1200, 'costcenter' => CostCenterNumber::Rtc],
        ['name' => 'RTC 3', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'price' => 1350, 'costcenter' => CostCenterNumber::Rtc],
        ['name' => 'RTC 4', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'price' => 1600, 'costcenter' => CostCenterNumber::Rtc],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $item) {
            Activity::factory()
                ->for(
                    BillableItem::factory()
                        ->state([
                            'description' => 'Activiteit: ' . $item['name'],
                            'price' => $item['price'],
                            'vat' => $item['price'] * 0.21,
                            'bill_period' => BillPeriod::Monthly,
                        ])
                        ->for(CostCenter::query()->where('number', $item['costcenter'])->firstOrFail()),
                )
                ->create([
                    'name' => $item['name'],
                    'start_date' => $item['start_date'],
                    'end_date' => $item['end_date'],
                ]);
        }
    }
}
