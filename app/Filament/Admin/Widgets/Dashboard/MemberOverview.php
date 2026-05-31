<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class MemberOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                label: __('labels.members'),
                value: Member::query()->count(),
            )->chart(
                $this->getMembersByCreatedByMonth()
            )->color('primary'),
        ];
    }

    private function getMembersByCreatedByMonth(): array
    {
        $start = CarbonImmutable::now()->subYear();

        return iterator_to_array(
            CarbonPeriodImmutable::create($start, CarbonImmutable::now())
                ->map(
                    fn (CarbonImmutable $date) =>
                        Member::query()
                            ->withTrashed()
                            ->where('created_at', '<=', $date)
                            ->where(
                                static fn (Builder $query) => $query
                                    ->whereNull('deleted_at')
                                    ->orWhere('deleted_at', '<=', $date)
                            )
                            ->count()
                )
        );
    }
}
