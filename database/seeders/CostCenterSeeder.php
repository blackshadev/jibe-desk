<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CostCenter;
use Illuminate\Database\Seeder;

final class CostCenterSeeder extends Seeder
{
    public function run(): void
    {
        CostCenter::factory()
            ->state(['description' => ''])
            ->createMany([
                ['number' => CostCenterNumber::Lessons, 'title' => 'Lesgelden'],
                ['number' => CostCenterNumber::Contribution, 'title' => 'Contributie leden'],
                ['number' => CostCenterNumber::Rtc, 'title' => 'Deelnemer bijdrage RTC'],
                ['number' => CostCenterNumber::RegistrationFee, 'title' => 'Inschrijf geld'],
                ['number' => CostCenterNumber::StorageRental, 'title' => 'Plankstalling'],
                ['number' => CostCenterNumber::Deposit, 'title' => 'Borg'],
            ]);
    }
}
