<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\CostCenterGroup;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Override;

final class CostCenterResults extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    #[Override]
    protected string $view = 'filament.admin.pages.cost-center-results';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBarSquare;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 1;

    public ?int $selectedYear = null;

    public ?int $selectedGroupId = null;

    #[Override]
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_bookkeeping_records') || auth()->user()?->can('view_any_cost_centers');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('labels.cost_center_results');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('labels.cost_center_results');
    }

    public function mount(): void
    {
        $this->selectedYear = now()->year;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('selectedYear')
                    ->label(__('labels.book_year'))
                    ->options($this->getAvailableYears(...))
                    ->default(now()->year)
                    ->live()
                    ->afterStateUpdated($this->resetTable(...)),
                Select::make('selectedGroupId')
                    ->label(__('labels.cost_center_group'))
                    ->options($this->getAvailableGroups(...))
                    ->placeholder(__('labels.all'))
                    ->live()
                    ->afterStateUpdated($this->resetTable(...)),
            ]);
    }

    /** @return array<string, string> */
    private function getAvailableYears(): array
    {
        $bookkeepingYears = BookkeepingRecord::query()
            ->select('year')
            ->distinct()
            ->pluck('year', 'year');

        $budgetYears = CostCenterBudget::query()
            ->select('year')
            ->distinct()
            ->pluck('year', 'year');

        $currentYear = collect([now()->year => now()->year]);

        return collect()
            ->merge($bookkeepingYears)
            ->merge($budgetYears)
            ->merge($currentYear)
            ->sortDesc()
            ->mapWithKeys(static fn ($year) => [$year => (string) $year])
            ->all();
    }

    /** @return array<int, string> */
    private function getAvailableGroups(): array
    {
        return CostCenterGroup::query()
            ->orderBy('number')
            ->get()
            ->mapWithKeys(static fn (CostCenterGroup $group): array => [$group->id => $group->label])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery(...))
            ->columns([
                TextColumn::make('number')
                    ->label(__('labels.number'))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('labels.title')),
                TextColumn::make('starting_amount')
                    ->label(__('labels.starting_amount'))
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize([
                        Summarizer::make('total_starting_amount')
                            ->hiddenLabel()
                            ->using($this->sumStartingAmount(...))
                            ->numeric()
                            ->money('EUR'),
                    ]),
                TextColumn::make('budget_amount')
                    ->label(__('labels.budget'))
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize([
                        Summarizer::make('total_budget')
                            ->hiddenLabel()
                            ->using($this->sumBudgetAmount(...))
                            ->numeric()
                            ->money('EUR'),
                    ]),
                TextColumn::make('total_amount')
                    ->label(__('labels.revenue'))
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize([
                        Summarizer::make('total_amount_sum')
                            ->hiddenLabel()
                            ->using($this->sumTotalAmount(...))
                            ->numeric()
                            ->money('EUR'),
                    ]),
                TextColumn::make('closing_balance')
                    ->label(__('labels.closing_balance'))
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize([
                        Summarizer::make('total_closing_balance')
                            ->hiddenLabel()
                            ->using($this->sumClosingBalance(...))
                            ->numeric()
                            ->money('EUR'),
                    ]),
                TextColumn::make('budget_difference')
                    ->label(__('labels.budget_difference'))
                    ->color(static fn (float $state): string => $state < 0 ? 'danger' : 'success')
                    ->money('EUR')
                    ->summarize([
                        Summarizer::make('total_budget_difference')
                            ->hiddenLabel()
                            ->using($this->sumBudgetDifference(...))
                            ->numeric()
                            ->money('EUR'),
                    ])
                    ->alignEnd()
                    ->sortable(),
            ])
            ->groups([
                Group::make('cost_center_group_number')
                    ->label(__('labels.cost_center_group'))
                    ->getKeyFromRecordUsing(static fn (CostCenter $record): string => (string) $record->getAttribute('cost_center_group_number'))
                    ->getTitleFromRecordUsing(
                        static fn (CostCenter $record): string => sprintf(
                            '%s %s',
                            (string) $record->getAttribute('cost_center_group_number'),
                            (string) $record->getAttribute('cost_center_group_name'),
                        ),
                    )
                    ->scopeQueryByKeyUsing(
                        static fn (EloquentBuilder $query, ?string $key): EloquentBuilder => blank($key)
                            ? $query
                            : $query->where('cost_center_groups.number', $key),
                    )
                    ->orderQueryUsing(
                        static fn (EloquentBuilder $query, string $direction): EloquentBuilder => $query->orderBy(
                            'cost_center_groups.number',
                            $direction,
                        ),
                    )
                    ->collapsible(),
            ])
            ->defaultGroup('cost_center_group_number');
    }

    private function getTableQuery(): EloquentBuilder
    {
        $budgetQuery = DB::table('cost_center_budgets')
            ->select('cost_center_id', 'starting_amount', 'budget_amount')
            ->where('year', $this->selectedYear);

        $bookkeepingQuery = DB::table('bookkeeping_records')
            ->selectRaw('cost_center_id, SUM(amount_price) as total_amount')
            ->where('year', $this->selectedYear)
            ->groupBy('cost_center_id');

        return CostCenter::query()
            ->join('cost_center_groups', 'cost_center_groups.id', '=', 'cost_centers.cost_center_group_id')
            ->leftJoinSub($budgetQuery, 'cb', 'cb.cost_center_id', '=', 'cost_centers.id')
            ->leftJoinSub($bookkeepingQuery, 'br', 'br.cost_center_id', '=', 'cost_centers.id')
            ->when(
                $this->selectedGroupId !== null,
                function (EloquentBuilder $query): void {
                    $group = CostCenterGroup::query()->find($this->selectedGroupId);

                    if ($group === null) {
                        return;
                    }

                    $query->whereIn('cost_centers.cost_center_group_id', $group->selfAndDescendantIds());
                },
            )
            ->select([
                'cost_centers.*',
                'cost_center_groups.number as cost_center_group_number',
                'cost_center_groups.name as cost_center_group_name',
                DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
                DB::raw('COALESCE(cb.budget_amount, 0) as budget_amount'),
                DB::raw('COALESCE(br.total_amount, 0) as total_amount'),
                DB::raw('COALESCE(cb.starting_amount, 0) + COALESCE(br.total_amount, 0) as closing_balance'),
                DB::raw('COALESCE(br.total_amount, 0) - COALESCE(cb.budget_amount, 0) as budget_difference'),
            ])
            ->orderBy('cost_centers.number');
    }

    private function sumStartingAmount(QueryBuilder $query): float
    {
        return (float) $this->summaryQuery($query)->sum('starting_amount');
    }

    private function sumBudgetAmount(QueryBuilder $query): float
    {
        return (float) $this->summaryQuery($query)->sum('budget_amount');
    }

    private function sumTotalAmount(QueryBuilder $query): float
    {
        return (float) $this->summaryQuery($query)->sum('total_amount');
    }

    private function sumClosingBalance(QueryBuilder $query): float
    {
        return (float) $this->summaryQuery($query)->sum('closing_balance');
    }

    private function sumBudgetDifference(QueryBuilder $query): float
    {
        return (float) $this->summaryQuery($query)->sum('budget_difference');
    }

    private function summaryQuery(QueryBuilder $query): QueryBuilder
    {
        $query = $query->clone();
        $query->columns = null;
        $query->groups = null;
        $query->orders = null;

        return $query;
    }

    #[Override]
    public function getBreadcrumbs(): array
    {
        return [
            url()->current() => __('labels.cost_center_results'),
        ];
    }
}
