<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MyUpcomingActivitiesWidget extends TableWidget
{
    #[Override]
    protected static ?int $sort = 21;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    #[Override]
    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.my_upcoming_activities'))
            ->query(
                static fn (): Builder => Activity::query()
                    ->where(static fn (Builder $query): Builder => $query->active())
                    ->whereHas(
                        'members',
                        static fn (Builder $query): Builder => $query
                            ->whereIn('members.id', auth()->user()?->member?->visibleMemberIds() ?? []),
                    )
                    ->orderBy('start_date'),
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name')),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.end_date'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->emptyStateHeading(__('labels.no_upcoming_activities'))
            ->paginated(false);
    }
}
