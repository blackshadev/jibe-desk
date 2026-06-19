<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\StorageSpaceLocations;

use App\Filament\Admin\Resources\StorageSpaceLocations\Pages\CreateStorageSpaceLocation;
use App\Filament\Admin\Resources\StorageSpaceLocations\Pages\EditStorageSpaceLocation;
use App\Filament\Admin\Resources\StorageSpaceLocations\Pages\ListStorageSpaceLocations;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class StorageSpaceLocationResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_storage_space_locations(): void
    {
        $this->withAuthorizedUser();

        StorageSpaceLocation::factory()->createOne(['name' => 'Container 3']);
        StorageSpaceLocation::factory()->createOne(['name' => 'Container 4']);

        Livewire::test(ListStorageSpaceLocations::class)
            ->assertCanSeeTableRecords(StorageSpaceLocation::all());
    }

    public function test_can_create_storage_space_location(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateStorageSpaceLocation::class)
            ->fillForm([
                'name' => 'Schuur Noord',
                'billableItem.description' => 'Opslagplek: Schuur Noord',
                'billableItem.price' => '0',
                'billableItem.bill_period' => 'annually',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('storage_space_locations', [
            'name' => 'Schuur Noord',
        ]);
    }

    public function test_can_edit_storage_space_location(): void
    {
        $this->withAuthorizedUser();

        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 3']);

        Livewire::test(EditStorageSpaceLocation::class, ['record' => $location->id])
            ->fillForm([
                'name' => 'Container 3 Updated',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('storage_space_locations', [
            'id' => $location->id,
            'name' => 'Container 3 Updated',
        ]);
    }

    public function test_can_delete_storage_space_location(): void
    {
        $this->withAuthorizedUser();

        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 3']);

        Livewire::test(EditStorageSpaceLocation::class, ['record' => $location->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('storage_space_locations', [
            'id' => $location->id,
        ]);
    }

    public function test_can_not_delete_storage_space_location_with_spaces(): void
    {
        $this->withAuthorizedUser();

        $location = StorageSpaceLocation::factory()
            ->has(StorageSpace::factory())
            ->createOne(['name' => 'Container 3']);

        Livewire::test(EditStorageSpaceLocation::class, ['record' => $location->id])
            ->assertActionHidden('delete');
    }
}
