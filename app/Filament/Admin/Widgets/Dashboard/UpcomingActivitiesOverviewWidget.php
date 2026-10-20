<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\Activity;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class UpcomingActivitiesOverviewWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 30;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    /**
     * @return list<RoleName>
     */
    #[Override]
    protected static function roles(): array
    {
        return [RoleName::ActivityAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $activities = Activity::query()
            ->where(static fn (Builder $query): Builder => $query->active())
            ->withCount('members')
            ->get();

        $participantCount = (int) $activities->sum('members_count');

        return [
            Stat::make(__('labels.upcoming_activities'), (string) $activities->count())
                ->description(trans_choice('labels.participant_count', $participantCount, ['count' => $participantCount]))
                ->color('primary'),
        ];
    }
}
