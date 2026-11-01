<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CostCenterGroup;
use Illuminate\Database\Seeder;

final class CostCenterGroupSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CostCenterGroupNumber::cases() as $number) {
            CostCenterGroup::query()->firstOrCreate(
                ['number' => $number->value],
                [
                    'external_number' => $number->value,
                    'name' => $number->name(),
                    'parent_id' => null,
                ],
            );
        }
    }
}
