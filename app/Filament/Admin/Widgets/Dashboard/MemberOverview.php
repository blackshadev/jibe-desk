<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MemberOverview extends StatsOverviewWidget
{
    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    #[Override]
    protected function getStats(): array
    {
        $pendingMembers = Member::query()->pending()->count();
        return [
            Stat::make(
                label: __('labels.members'),
                value: Member::query()->active()->count(),
            )
                ->description(trans_choice('labels.new_member_count', $pendingMembers, ['count' => $pendingMembers]))
                ->descriptionColor('success')
                ->chart(
                    $this->getMembersByCreatedByMonth(),
                )
                ->color('primary'),
        ];
    }

    private function getMembersByCreatedByMonth(): array
    {
        $start = CarbonImmutable::now()->subYear();

        return iterator_to_array(
            CarbonPeriodImmutable::create($start, CarbonImmutable::now())
                ->map(
                    static fn (CarbonImmutable $date) => Member::query()
                        ->where('created_at', '<=', $date)
                        ->where(
                            static fn (Builder $query) => $query
                                ->whereNull('stopped_at')
                                ->orWhere('stopped_at', '>', $date),
                        )
                        ->count(),
                ),
        );
    }
}
