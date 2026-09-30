<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Pages\CostCenterResults;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CostCenterResultsPageTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_whole_year_totals(): void
    {
        $this->withAuthorizedUser();
        $year = now()->year;

        $first = CostCenter::factory()->create();
        $second = CostCenter::factory()->create();

        CostCenterBudget::factory()->createMany([
            ['cost_center_id' => $first->id, 'year' => $year, 'starting_amount' => 1000, 'budget_amount' => 5000],
            ['cost_center_id' => $second->id, 'year' => $year, 'starting_amount' => 500, 'budget_amount' => 2500],
        ]);

        BookkeepingRecord::factory()->createMany([
            ['cost_center_id' => $first->id, 'year' => $year, 'amount_price' => 2000, 'amount_vat' => 0],
            ['cost_center_id' => $second->id, 'year' => $year, 'amount_price' => 300, 'amount_vat' => 0],
        ]);

        Livewire::test(CostCenterResults::class)
            ->assertTableColumnSummarySet('starting_amount', 'total_starting_amount', 1500.00)
            ->assertTableColumnSummarySet('budget_amount', 'total_budget', 7500.00)
            ->assertTableColumnSummarySet('total_amount', 'total_amount_sum', 2300.00)
            ->assertTableColumnSummarySet('closing_balance', 'total_closing_balance', 3800.00);
    }

    public function test_total_amount_is_rendered_red_when_over_budget(): void
    {
        $this->withAuthorizedUser();
        $year = now()->year;

        $costCenter = CostCenter::factory()->create(['number' => '1000']);
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => $year,
            'starting_amount' => 0,
            'budget_amount' => 200,
        ]);
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => $year,
            'amount_price' => 150,
            'amount_vat' => 0,
        ]);

        Livewire::test(CostCenterResults::class)
            ->assertSeeHtml('fi-color-danger');
    }
}
