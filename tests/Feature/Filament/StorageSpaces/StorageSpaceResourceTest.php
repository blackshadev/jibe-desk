<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\StorageSpaces;

use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class StorageSpaceResourceTest extends FeatureTestCase
{
    public function test_can_list_storage_spaces(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 3']);

        StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 1]);
        StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 2]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords(StorageSpace::all());
    }

    public function test_can_create_storage_space(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Schuur Noord']);

        Livewire::test(ListStorageSpaces::class)
            ->callAction('create', [
                'storage_space_location_id' => $location->id,
                'number' => 42,
            ]);

        $this->assertDatabaseHas('storage_spaces', [
            'storage_space_location_id' => $location->id,
            'number' => 42,
        ]);
    }

    public function test_can_bulk_generate_storage_spaces(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 5']);

        Livewire::test(ListStorageSpaces::class)
            ->callAction('generate_storage_spaces', [
                'storage_space_location_id' => $location->id,
                'from_number' => 1,
                'to_number' => 10,
            ]);

        $this->assertDatabaseCount('storage_spaces', 10);
        for ($i = 1; $i <= 10; $i++) {
            $this->assertDatabaseHas('storage_spaces', [
                'storage_space_location_id' => $location->id,
                'number' => $i,
            ]);
        }
    }
}
