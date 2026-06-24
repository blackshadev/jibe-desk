<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Navigation\NavigationGroup;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;
use UnitEnum;

final class CostCenterResults extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.cost-center-results';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    public ?int $selectedYear = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_bookkeeping_records') || auth()->user()?->can('view_any_cost_centers') ?? false;
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
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('labels.total_amount'))
                    ->money('EUR'),
                TextColumn::make('result')
                    ->label(__('labels.result'))
                    ->money('EUR'),
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
            ->groupBy('cost_centers.id', 'cost_centers.number', 'cost_centers.title', 'cb.starting_amount')
            ->select(
                'cost_centers.id',
                'cost_centers.number',
                'cost_centers.title',
                DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
                DB::raw('COALESCE(SUM(br.amount_price), 0) as total_amount'),
                DB::raw('COALESCE(cb.starting_amount, 0) + COALESCE(SUM(br.amount_price), 0) as result'),
            )
            ->orderBy('cost_centers.number');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url()->current() => __('labels.cost_center_results'),
        ];
    }
}
