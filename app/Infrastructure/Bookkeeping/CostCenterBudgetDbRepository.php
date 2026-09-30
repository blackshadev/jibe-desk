<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping;

use App\Domain\Bookkeeping\CostCenterBudgetRepository;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;

final class CostCenterBudgetDbRepository implements CostCenterBudgetRepository
{
    #[Override]
    public function createZeroBudgetsForYear(int $year): int
    {
        $timestamp = DB::escape(now()->format('c'));

        return CostCenterBudget::query()->insertUsing(
            ['year', 'cost_center_id', 'starting_amount', 'budget_amount', 'created_at', 'updated_at'],
            CostCenter::query()
                ->whereNotExists(static function (Builder $query) use ($year): void {
                    $query
                        ->from('cost_center_budgets')
                        ->whereColumn('cost_center_budgets.cost_center_id', 'cost_centers.id')
                        ->where('cost_center_budgets.year', $year);
                })
                ->select(
                    DB::raw((string) $year),
                    'cost_centers.id',
                    DB::raw('0'),
                    DB::raw('0'),
                    DB::raw($timestamp),
                    DB::raw($timestamp),
                ),
        );
    }
}
