<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\CostCenterGroup;
use Illuminate\Database\Seeder;

final class CostCenterSeeder extends Seeder
{
    public function run(): void
    {
        $groupIds = CostCenterGroup::query()
            ->whereIn('number', [
                CostCenterGroupNumber::OtherReceivables->value,
                CostCenterGroupNumber::LessonIncome->value,
                CostCenterGroupNumber::GeneralIncome->value,
            ])
            ->pluck('id', 'number');

        CostCenter::factory()
            ->state(['description' => ''])
            ->has(CostCenterBudget::factory()->state([
                'starting_amount' => 0,
                'budget_amount' => 0,
                'year' => now()->year,
            ]), 'budgets')
            ->createMany([
                [
                    'number' => CostCenterNumber::Lessons,
                    'title' => 'Lesgelden',
                    'cost_center_group_id' => $groupIds[CostCenterGroupNumber::LessonIncome->value],
                ],
                [
                    'number' => CostCenterNumber::Contribution,
                    'title' => 'Contributie leden',
                    'cost_center_group_id' => $groupIds[CostCenterGroupNumber::GeneralIncome->value],
                ],
                [
                    'number' => CostCenterNumber::Rtc,
                    'title' => 'Deelnemer bijdrage RTC',
                    'cost_center_group_id' => $groupIds[CostCenterGroupNumber::GeneralIncome->value],
                ],
                [
                    'number' => CostCenterNumber::RegistrationFee,
                    'title' => 'Inschrijf geld',
                    'cost_center_group_id' => $groupIds[CostCenterGroupNumber::GeneralIncome->value],
                ],
                [
                    'number' => CostCenterNumber::StorageRental,
                    'title' => 'Plankstalling',
                    'cost_center_group_id' => $groupIds[CostCenterGroupNumber::GeneralIncome->value],
                ],
                [
                    'number' => CostCenterNumber::Deposit,
                    'title' => 'Borg',
                    'cost_center_group_id' => $groupIds[CostCenterGroupNumber::OtherReceivables->value],
                ],
            ]);
    }
}
