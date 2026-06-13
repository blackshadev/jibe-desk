<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Override;

final class Dasboard extends BaseDashboard
{
    #[Override]
    public function getWidgets(): array
    {
        return [
            MemberOverview::class,
        ];
    }
}
