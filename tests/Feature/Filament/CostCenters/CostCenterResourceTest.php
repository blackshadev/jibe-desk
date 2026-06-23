<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\CostCenters;

use App\Filament\Admin\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\ListCostCenters;
use App\Models\CostCenter;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CostCenterResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_cost_centers(): void
    {
        $this->withAuthorizedUser();

        CostCenter::factory()->createOne(['number' => '1000', 'title' => 'Contributie']);
        CostCenter::factory()->createOne(['number' => '2000', 'title' => 'Activiteiten']);

        Livewire::test(ListCostCenters::class)
            ->assertCanSeeTableRecords(CostCenter::all());
    }

    public function test_can_create_cost_center(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateCostCenter::class)
            ->fillForm([
                'number' => '1000',
                'title' => 'Contributie',
                'description' => 'Lidmaatschapscontributie',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cost_centers', [
            'number' => '1000',
            'title' => 'Contributie',
        ]);
    }

    public function test_can_edit_cost_center(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->createOne(['number' => '1000', 'title' => 'Contributie']);

        Livewire::test(EditCostCenter::class, ['record' => $costCenter->id])
            ->fillForm([
                'title' => 'Contributie Updated',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cost_centers', [
            'id' => $costCenter->id,
            'title' => 'Contributie Updated',
        ]);
    }

    public function test_can_delete_cost_center(): void
    {
        $this->withAuthorizedUser();

        $costCenter = CostCenter::factory()->createOne(['number' => '1000', 'title' => 'Contributie']);

        Livewire::test(EditCostCenter::class, ['record' => $costCenter->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('cost_centers', [
            'id' => $costCenter->id,
        ]);
    }
}
