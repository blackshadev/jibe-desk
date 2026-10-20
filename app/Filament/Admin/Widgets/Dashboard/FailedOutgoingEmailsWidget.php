<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\OutgoingEmail;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class FailedOutgoingEmailsWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 33;

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
        return [RoleName::TechnicalAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $count = OutgoingEmail::query()
            ->where('status', OutgoingEmailStatus::Failed)
            ->count();

        return [
            Stat::make(__('labels.failed_emails'), (string) $count)
                ->description($count > 0 ? __('labels.failed_emails_description') : null)
                ->color($count > 0 ? 'danger' : 'success'),
        ];
    }
}
