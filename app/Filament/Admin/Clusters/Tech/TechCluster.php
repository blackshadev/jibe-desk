<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Tech;

use App\Domain\Authorization\RoleName;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Override;

final class TechCluster extends Cluster
{
    #[Override]
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    #[Override]
    protected static ?int $navigationSort = 7;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::WrenchScrewdriver;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('labels.navigation_groups.technical');
    }

    #[Override]
    public static function getClusterBreadcrumb(): string
    {
        return __('labels.navigation_groups.technical');
    }

    #[Override]
    public static function canAccessClusteredComponents(): bool
    {
        return auth()->user()?->hasRole(RoleName::TechnicalAdministration) ?? false;
    }
}
