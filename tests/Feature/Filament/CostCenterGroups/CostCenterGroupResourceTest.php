<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\CostCenterGroups;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource;
use App\Filament\Admin\Resources\CostCenterGroups\Pages\CreateCostCenterGroup;
use App\Filament\Admin\Resources\CostCenterGroups\Pages\EditCostCenterGroup;
use App\Filament\Admin\Resources\CostCenterGroups\Pages\ListCostCenterGroups;
use App\Models\CostCenterGroup;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CostCenterGroupResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_cost_center_groups(): void
    {
        $this->withAuthorizedUser();

        $first = CostCenterGroup::factory()->createOne(['number' => '900']);
        $second = CostCenterGroup::factory()->createOne(['number' => '901']);

        Livewire::test(ListCostCenterGroups::class)
            ->assertCanSeeTableRecords([$first, $second]);
    }

    public function test_can_create_cost_center_group(): void
    {
        $this->withAuthorizedUser();
        $parent = CostCenterGroup::factory()->createOne(['number' => '900']);

        Livewire::test(CreateCostCenterGroup::class)
            ->fillForm([
                'number' => '900.910',
                'name' => 'Materiaal',
                'parent_id' => $parent->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cost_center_groups', [
            'number' => '900.910',
            'external_number' => null,
            'name' => 'Materiaal',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_can_edit_cost_center_group(): void
    {
        $this->withAuthorizedUser();
        $group = CostCenterGroup::factory()->createOne(['number' => '900', 'name' => 'Oud']);

        Livewire::test(EditCostCenterGroup::class, ['record' => $group->id])
            ->fillForm(['name' => 'Nieuw'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cost_center_groups', [
            'id' => $group->id,
            'name' => 'Nieuw',
        ]);
    }

    public function test_edit_page_shows_the_parent_hierarchy_with_clickable_groups(): void
    {
        $this->withAuthorizedUser();
        $root = CostCenterGroup::factory()->createOne(['number' => '900', 'name' => 'Root']);
        $child = CostCenterGroup::factory()->createOne([
            'number' => '900.910',
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        Livewire::test(EditCostCenterGroup::class, ['record' => $child->id])
            ->assertSee('900 Root')
            ->assertSee('900.910 Child')
            ->assertSeeHtml(CostCenterGroupResource::getUrl('edit', ['record' => $root]))
            ->assertSeeHtml(CostCenterGroupResource::getUrl('edit', ['record' => $child]));
    }

    public function test_can_delete_an_empty_cost_center_group(): void
    {
        $this->withAuthorizedUser();
        $group = CostCenterGroup::factory()->createOne(['number' => '900']);

        Livewire::test(EditCostCenterGroup::class, ['record' => $group->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('cost_center_groups', ['id' => $group->id]);
    }

    public function test_the_parent_select_rejects_a_descendant(): void
    {
        $this->withAuthorizedUser();
        $parent = CostCenterGroup::factory()->createOne(['number' => '900']);
        $child = CostCenterGroup::factory()->createOne(['number' => '900.910', 'parent_id' => $parent->id]);

        Livewire::test(EditCostCenterGroup::class, ['record' => $parent->id])
            ->fillForm(['parent_id' => $child->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);
    }

    public function test_member_administration_can_only_view_cost_center_groups(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);
        $group = CostCenterGroup::factory()->createOne();

        static::assertTrue(Gate::allows('viewAny', CostCenterGroup::class));
        static::assertTrue(Gate::allows('view', $group));
        static::assertFalse(Gate::allows('create', CostCenterGroup::class));
        static::assertFalse(Gate::allows('update', $group));
        static::assertFalse(Gate::allows('delete', $group));
        static::assertFalse(Gate::allows('deleteAny', CostCenterGroup::class));
    }

    public function test_financial_administration_has_full_access_to_cost_center_groups(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $group = CostCenterGroup::factory()->createOne();

        static::assertTrue(Gate::allows('viewAny', CostCenterGroup::class));
        static::assertTrue(Gate::allows('view', $group));
        static::assertTrue(Gate::allows('create', CostCenterGroup::class));
        static::assertTrue(Gate::allows('update', $group));
        static::assertTrue(Gate::allows('delete', $group));
        static::assertTrue(Gate::allows('deleteAny', CostCenterGroup::class));
    }
}
