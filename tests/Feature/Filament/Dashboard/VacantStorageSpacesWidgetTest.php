<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\VacantStorageSpacesWidget;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class VacantStorageSpacesWidgetTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_it_counts_spaces_without_a_rental(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        StorageSpace::factory()->createQuietly();
        $rented = StorageSpace::factory()->createQuietly();

        StorageSpaceRental::factory()->createQuietly([
            'storage_space_id' => $rented->id,
            'start_date' => now()->subMonth(),
            'end_date' => null,
        ]);

        Livewire::test(VacantStorageSpacesWidget::class)
            ->assertSee('1');
    }

    public function test_it_is_only_visible_for_rental_administration(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);
        static::assertTrue(VacantStorageSpacesWidget::canView());

        $this->withUserHavingRole(RoleName::MemberAdministration);
        static::assertFalse(VacantStorageSpacesWidget::canView());
    }
}
