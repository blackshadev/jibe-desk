<?php

declare(strict_types=1);

namespace Tests\Feature\StorageSpaces;

use App\Filament\Admin\Resources\StorageSpaces\Actions\GenerateStorageSpacesAction;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use Tests\FeatureTestCase;

final class GenerateStorageSpacesActionTest extends FeatureTestCase
{
    public function test_creates_spaces_for_given_range(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 3']);

        $action = GenerateStorageSpacesAction::make();

        $action->getActionFunction()(['storage_space_location_id' => $location->id, 'from_number' => 1, 'to_number' => 5]);

        $this->assertDatabaseCount('storage_spaces', 5);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseHas('storage_spaces', [
                'storage_space_location_id' => $location->id,
                'number' => $i,
            ]);
        }
    }

    public function test_skips_existing_combinations(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 3']);
        StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 3]);
        StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 5]);
        StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 7]);

        $action = GenerateStorageSpacesAction::make();

        $action->getActionFunction()(['storage_space_location_id' => $location->id, 'from_number' => 1, 'to_number' => 10]);

        $this->assertDatabaseCount('storage_spaces', 10);
        for ($i = 1; $i <= 10; $i++) {
            $this->assertDatabaseHas('storage_spaces', [
                'storage_space_location_id' => $location->id,
                'number' => $i,
            ]);
        }
    }

    public function test_creates_spaces_for_single_number_range(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 4']);

        $action = GenerateStorageSpacesAction::make();

        $action->getActionFunction()(['storage_space_location_id' => $location->id, 'from_number' => 1, 'to_number' => 1]);

        $this->assertDatabaseCount('storage_spaces', 1);
        $this->assertDatabaseHas('storage_spaces', [
            'storage_space_location_id' => $location->id,
            'number' => 1,
        ]);
    }
}
