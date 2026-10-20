<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Dashboard\DashboardWidgets;
use Filament\Pages\Dashboard as BaseDashboard;
use Override;

final class Dashboard extends BaseDashboard
{
    #[Override]
    protected static string $routePath = 'dashboard';

    #[Override]
    protected static ?int $navigationSort = -10;

    #[Override]
    public function getWidgets(): array
    {
        return DashboardWidgets::all();
    }

    #[Override]
    public function getColumns(): int
    {
        return 3;
    }
}
