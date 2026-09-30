<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\CostCenters;

use App\Filament\Admin\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Admin\Resources\CostCenters\RelationManagers\CostCenterBudgetsRelationManager;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CostCenterBudgetsRelationManagerTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_lists_budgets_for_the_cost_center(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        $budget = CostCenterBudget::factory()->create(['cost_center_id' => $costCenter->id]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->assertCanSeeTableRecords([$budget]);
    }

    public function test_can_create_budget(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('create', data: [
                'year' => 2027,
                'starting_amount' => 1000,
                'budget_amount' => 5000,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $costCenter->id,
            'year' => 2027,
            'starting_amount' => 1000,
            'budget_amount' => 5000,
        ]);
    }

    public function test_can_edit_budget(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        $budget = CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'starting_amount' => 1000,
            'budget_amount' => 5000,
        ]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('edit', $budget, data: [
                'budget_amount' => 7500,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('cost_center_budgets', [
            'id' => $budget->id,
            'budget_amount' => 7500,
        ]);
    }

    public function test_can_delete_budget(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        $budget = CostCenterBudget::factory()->create(['cost_center_id' => $costCenter->id]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('delete', $budget);

        $this->assertDatabaseMissing('cost_center_budgets', ['id' => $budget->id]);
    }

    public function test_cannot_create_duplicate_budget_for_same_year(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
        ]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('create', data: [
                'year' => 2026,
                'starting_amount' => 1000,
                'budget_amount' => 5000,
            ])
            ->assertHasTableActionErrors(['year' => 'unique']);
    }
}
