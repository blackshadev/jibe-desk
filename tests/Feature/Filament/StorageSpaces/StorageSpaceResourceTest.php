<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\StorageSpaces;

use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\StorageSpaceRental;
use Carbon\Carbon;
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

    public function test_shows_member_name_when_storage_space_is_rented(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container 1']);
        $space = StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 5]);
        $member = Member::factory()->createOneQuietly(['first_name' => 'Jan', 'infix_name' => '', 'last_name' => 'Jansen']);

        StorageSpaceRental::factory()->createOneQuietly([
            'storage_space_id' => $space->id,
            'member_id' => $member->id,
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords([$space])
            ->assertCanRenderTableColumn('currentRental.member.name')
            ->assertTableColumnStateSet('currentRental.member.name', 'Jansen, Jan', $space);
    }

    public function test_shows_dash_for_rented_until_when_no_rental(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Locatie']);
        $space = StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords([$space])
            ->assertCanRenderTableColumn('currentRental.end_date');
    }

    public function test_available_tab_only_shows_available_spaces(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container']);
        $available = StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 1]);
        $rented = StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 2]);

        StorageSpaceRental::factory()->createOneQuietly([
            'storage_space_id' => $rented->id,
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords([$available, $rented])
            ->tap(static fn (mixed $livewire) => $livewire->set('activeTab', 'available'))
            ->assertCanSeeTableRecords([$available])
            ->assertCanNotSeeTableRecords([$rented]);
    }

    public function test_unavailable_tab_only_shows_rented_spaces(): void
    {
        $location = StorageSpaceLocation::factory()->createOne(['name' => 'Container']);
        $available = StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 1]);
        $rented = StorageSpace::factory()->createOne(['storage_space_location_id' => $location->id, 'number' => 2]);

        StorageSpaceRental::factory()->createOneQuietly([
            'storage_space_id' => $rented->id,
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords([$available, $rented])
            ->tap(static fn (mixed $livewire) => $livewire->set('activeTab', 'unavailable'))
            ->assertCanSeeTableRecords([$rented])
            ->assertCanNotSeeTableRecords([$available]);
    }

    public function test_location_filter_filters_by_location(): void
    {
        $locationA = StorageSpaceLocation::factory()->createOne(['name' => 'Locatie A']);
        $locationB = StorageSpaceLocation::factory()->createOne(['name' => 'Locatie B']);

        $spaceA = StorageSpace::factory()->createOne(['storage_space_location_id' => $locationA->id, 'number' => 1]);
        $spaceB = StorageSpace::factory()->createOne(['storage_space_location_id' => $locationB->id, 'number' => 2]);

        Livewire::test(ListStorageSpaces::class)
            ->assertCanSeeTableRecords([$spaceA, $spaceB])
            ->set('tableFilters.storage_space_location_id.value', (string) $locationA->id)
            ->assertCanSeeTableRecords([$spaceA])
            ->assertCanNotSeeTableRecords([$spaceB]);
    }
}
