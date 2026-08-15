<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

final class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        InventoryCategory::factory()->createMany([
            ['name' => 'Windsurf materiaal'],
            ['name' => 'Boot materialen'],
            ['name' => 'Apparaten'],
            ['name' => 'Inboedel'],
        ]);
    }
}
