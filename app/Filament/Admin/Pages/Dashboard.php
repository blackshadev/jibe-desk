<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Override;

final class Dashboard extends BaseDashboard
{
    #[Override]
    protected static ?int $navigationSort = -10;

    #[Override]
    public function getWidgets(): array
    {
        return [
            MemberOverview::class,
        ];
    }
}
