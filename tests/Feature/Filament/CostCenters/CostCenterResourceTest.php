<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\CostCenters;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource;
use App\Filament\Admin\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\ListCostCenters;
use App\Models\CostCenter;
use App\Models\CostCenterGroup;
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
        $group = CostCenterGroup::factory()->createOne();

        Livewire::test(CreateCostCenter::class)
            ->fillForm([
                'number' => '1000',
                'title' => 'Contributie',
                'description' => 'Lidmaatschapscontributie',
                'cost_center_group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cost_centers', [
            'number' => '1000',
            'title' => 'Contributie',
            'cost_center_group_id' => $group->id,
        ]);
    }

    public function test_a_cost_center_requires_a_group(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateCostCenter::class)
            ->fillForm([
                'number' => '1000',
                'title' => 'Contributie',
            ])
            ->call('create')
            ->assertHasFormErrors(['cost_center_group_id' => 'required']);
    }

    public function test_the_group_filter_includes_the_selected_group_subtree(): void
    {
        $this->withAuthorizedUser();

        $parent = CostCenterGroup::factory()->createOne(['number' => '900']);
        $child = CostCenterGroup::factory()->createOne(['number' => '900.910', 'parent_id' => $parent->id]);
        $other = CostCenterGroup::factory()->createOne(['number' => '950']);

        $parentCostCenter = CostCenter::factory()->createOne(['cost_center_group_id' => $parent->id]);
        $childCostCenter = CostCenter::factory()->createOne(['cost_center_group_id' => $child->id]);
        $otherCostCenter = CostCenter::factory()->createOne(['cost_center_group_id' => $other->id]);

        Livewire::test(ListCostCenters::class)
            ->filterTable('cost_center_group_id', $parent->id)
            ->assertCanSeeTableRecords([$parentCostCenter, $childCostCenter])
            ->assertCanNotSeeTableRecords([$otherCostCenter]);
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

    public function test_edit_page_shows_the_cost_center_group_hierarchy_with_clickable_groups(): void
    {
        $this->withAuthorizedUser();
        $root = CostCenterGroup::factory()->createOne(['number' => '900', 'name' => 'Root']);
        $child = CostCenterGroup::factory()->createOne([
            'number' => '900.910',
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);
        $costCenter = CostCenter::factory()->createOne(['cost_center_group_id' => $child->id]);

        Livewire::test(EditCostCenter::class, ['record' => $costCenter->id])
            ->assertSee('900 Root')
            ->assertSee('900.910 Child')
            ->assertSeeHtml(CostCenterGroupResource::getUrl('edit', ['record' => $root]))
            ->assertSeeHtml(CostCenterGroupResource::getUrl('edit', ['record' => $child]));
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

    public function test_list_page_has_create_budgets_for_year_action(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListCostCenters::class)
            ->assertActionVisible('createCostCenterBudgets');
    }

    public function test_create_budgets_for_year_action_creates_zeroed_budgets_for_every_cost_center(): void
    {
        $this->withAuthorizedUser();

        $firstCostCenter = CostCenter::factory()->createOne();
        $secondCostCenter = CostCenter::factory()->createOne();

        Livewire::test(ListCostCenters::class)
            ->callAction('createCostCenterBudgets', data: ['year' => 2027]);

        $this->assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $firstCostCenter->id,
            'year' => 2027,
            'starting_amount' => 0,
            'budget_amount' => 0,
        ]);
        $this->assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $secondCostCenter->id,
            'year' => 2027,
            'starting_amount' => 0,
            'budget_amount' => 0,
        ]);
    }

    public function test_create_budgets_for_year_action_is_hidden_without_budget_permission(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListCostCenters::class)
            ->assertActionHidden('createCostCenterBudgets');
    }
}
