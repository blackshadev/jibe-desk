<?php

declare(strict_types=1);

namespace Tests\Feature\StorageSpaces;

use App\Filament\Admin\Resources\StorageSpaces\Actions\GenerateStorageSpacesAction;
use App\Models\StorageSpace;
use Tests\FeatureTestCase;

final class GenerateStorageSpacesActionTest extends FeatureTestCase
{
    public function test_creates_spaces_for_given_range(): void
    {
        $action = GenerateStorageSpacesAction::make();

        $action->getActionFunction()(['location' => 'Container 3', 'from_number' => 1, 'to_number' => 5]);

        $this->assertDatabaseCount('storage_spaces', 5);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseHas('storage_spaces', [
                'location' => 'Container 3',
                'number' => $i,
            ]);
        }
    }

    public function test_skips_existing_combinations(): void
    {
        StorageSpace::factory()->createOne(['location' => 'Container 3', 'number' => 3]);
        StorageSpace::factory()->createOne(['location' => 'Container 3', 'number' => 5]);
        StorageSpace::factory()->createOne(['location' => 'Container 3', 'number' => 7]);

        $action = GenerateStorageSpacesAction::make();

        $action->getActionFunction()(['location' => 'Container 3', 'from_number' => 1, 'to_number' => 10]);

        $this->assertDatabaseCount('storage_spaces', 10);
        for ($i = 1; $i <= 10; $i++) {
            $this->assertDatabaseHas('storage_spaces', [
                'location' => 'Container 3',
                'number' => $i,
            ]);
        }
    }

    public function test_creates_spaces_for_single_number_range(): void
    {
        $action = GenerateStorageSpacesAction::make();

        $action->getActionFunction()(['location' => 'Container 4', 'from_number' => 1, 'to_number' => 1]);

        $this->assertDatabaseCount('storage_spaces', 1);
        $this->assertDatabaseHas('storage_spaces', [
            'location' => 'Container 4',
            'number' => 1,
        ]);
    }
}
