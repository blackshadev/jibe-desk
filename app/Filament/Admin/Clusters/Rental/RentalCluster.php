<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Rental;

use App\Domain\Authorization\RoleName;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Override;

final class RentalCluster extends Cluster
{
    #[Override]
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    #[Override]
    protected static ?int $navigationSort = 4;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('labels.navigation_groups.rental');
    }

    #[Override]
    public static function getClusterBreadcrumb(): string
    {
        return __('labels.navigation_groups.rental');
    }

    #[Override]
    public static function canAccessClusteredComponents(): bool
    {
        return auth()
            ->user()
            ?->hasRole([
                RoleName::RentalAdministration,
                RoleName::TechnicalAdministration,
            ]) ?? false;
    }
}
