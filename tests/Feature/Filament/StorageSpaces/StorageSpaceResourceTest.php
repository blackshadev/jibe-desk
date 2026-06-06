<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\StorageSpaces;

use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Models\StorageSpace;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class StorageSpaceResourceTest extends FeatureTestCase
{
    public function test_can_list_storage_spaces(): void
    {
        StorageSpace::factory()->createOne(['location' => 'Container 3', 'number' => 1]);
        StorageSpace::factory()->createOne(['location' => 'Container 3', 'number' => 2]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords(StorageSpace::all());
    }

    public function test_can_create_storage_space(): void
    {
        Livewire::test(ListStorageSpaces::class)
            ->callAction('create', [
                'location' => 'Schuur Noord',
                'number' => 42,
            ]);

        $this->assertDatabaseHas('storage_spaces', [
            'location' => 'Schuur Noord',
            'number' => 42,
        ]);
    }

    public function test_can_bulk_generate_storage_spaces(): void
    {
        Livewire::test(ListStorageSpaces::class)
            ->callAction('generate_storage_spaces', [
                'location' => 'Container 5',
                'from_number' => 1,
                'to_number' => 10,
            ]);

        $this->assertDatabaseCount('storage_spaces', 10);
        for ($i = 1; $i <= 10; $i++) {
            $this->assertDatabaseHas('storage_spaces', [
                'location' => 'Container 5',
                'number' => $i,
            ]);
        }
    }
}
