<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Bookkeeping;

use App\Infrastructure\Bookkeeping\CostCenterBudgetDbRepository;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Override;
use Tests\FeatureTestCase;

final class CostCenterBudgetDbRepositoryTest extends FeatureTestCase
{
    private CostCenterBudgetDbRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CostCenterBudgetDbRepository();
    }

    public function test_create_zero_budgets_for_year_creates_a_zeroed_budget_for_every_cost_center(): void
    {
        $firstCostCenter = CostCenter::factory()->create();
        $secondCostCenter = CostCenter::factory()->create();

        $created = $this->repository->createZeroBudgetsForYear(2027);

        static::assertSame(2, $created);
        static::assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $firstCostCenter->id,
            'year' => 2027,
            'starting_amount' => 0,
            'budget_amount' => 0,
        ]);
        static::assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $secondCostCenter->id,
            'year' => 2027,
            'starting_amount' => 0,
            'budget_amount' => 0,
        ]);
    }

    public function test_create_zero_budgets_for_year_keeps_existing_budgets_intact(): void
    {
        $existingCostCenter = CostCenter::factory()->create();
        $missingCostCenter = CostCenter::factory()->create();
        CostCenterBudget::factory()->create([
            'cost_center_id' => $existingCostCenter->id,
            'year' => 2027,
            'starting_amount' => 500,
            'budget_amount' => 900,
        ]);

        $created = $this->repository->createZeroBudgetsForYear(2027);

        static::assertSame(1, $created);
        static::assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $existingCostCenter->id,
            'year' => 2027,
            'starting_amount' => 500,
            'budget_amount' => 900,
        ]);
        static::assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $missingCostCenter->id,
            'year' => 2027,
            'starting_amount' => 0,
            'budget_amount' => 0,
        ]);
    }

    public function test_create_zero_budgets_for_year_returns_zero_when_every_cost_center_already_has_a_budget(): void
    {
        $costCenter = CostCenter::factory()->create();
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2027,
        ]);

        static::assertSame(0, $this->repository->createZeroBudgetsForYear(2027));
    }
}
