<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            ]);
    }

    private function getTableQuery(): Builder
    {
        return CostCenter::query()
            ->leftJoin('cost_center_budgets as cb', function ($join): void {
                $join->on('cb.cost_center_id', '=', 'cost_centers.id')
                    ->where('cb.year', '=', $this->selectedYear);
            })
            ->leftJoin('bookkeeping_records as br', function ($join): void {
                $join->on('br.cost_center_id', '=', 'cost_centers.id')
                    ->where('br.year', '=', $this->selectedYear);
            })
            ->groupBy('cost_centers.id', 'cost_centers.number', 'cost_centers.title', 'cb.starting_amount', 'cb.budget_amount')
            ->select(
                'cost_centers.id',
                'cost_centers.number',
                'cost_centers.title',
                DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
                DB::raw('COALESCE(cb.budget_amount, 0) as budget_amount'),
                DB::raw('COALESCE(SUM(br.amount_price), 0) as total_amount'),
                DB::raw('COALESCE(cb.starting_amount, 0) + COALESCE(SUM(br.amount_price), 0) as closing_balance'),
                DB::raw('COALESCE(SUM(br.amount_price), 0) - COALESCE(cb.budget_amount, 0) as budget_difference'),
            )
            ->orderBy('cost_centers.number');
    }

    private function sumStartingAmount(): float
    {
        return (float) CostCenterBudget::query()
            ->where('year', $this->selectedYear)
            ->sum('starting_amount');
    }

    private function sumBudgetAmount(): float
    {
        return (float) CostCenterBudget::query()
            ->where('year', $this->selectedYear)
            ->sum('budget_amount');
    }

    private function sumTotalAmount(): float
    {
        return (float) BookkeepingRecord::query()
            ->where('year', $this->selectedYear)
            ->sum('amount_price');
    }

    private function sumClosingBalance(): float
    {
        return $this->sumStartingAmount() + $this->sumTotalAmount();
    }

    private function sumBudgetDifference(): float
    {
        return $this->sumTotalAmount() - $this->sumBudgetAmount();
    }

    #[Override]
    public function getBreadcrumbs(): array
    {
        return [
            url()->current() => __('labels.cost_center_results'),
        ];
    }
}
