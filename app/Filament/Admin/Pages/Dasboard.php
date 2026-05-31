<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dasboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            MemberOverview::class,
        ];
    }
}
