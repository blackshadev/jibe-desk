<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Activities;

use App\Domain\Authorization\RoleName;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Override;

final class ActivitiesCluster extends Cluster
{
    #[Override]
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    #[Override]
    protected static ?int $navigationSort = 5;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('labels.navigation_groups.activities');
    }

    #[Override]
    public static function getClusterBreadcrumb(): string
    {
        return __('labels.navigation_groups.activities');
    }

    #[Override]
    public static function canAccessClusteredComponents(): bool
    {
        return auth()->user()?->hasRole(RoleName::ActivityAdministration) ?? false;
    }
}
