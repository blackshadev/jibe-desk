<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use Filament\Widgets\Widget;
use Override;

final class MembershipCardWidget extends Widget
{
    #[Override]
    protected static ?int $sort = 23;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 8,
    ];

    #[Override]
    protected string $view = 'filament.admin.widgets.dashboard.membership-card';

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $user = auth()->user();
        $member = $user?->member;
        $household = $member?->household;

        return [
            'memberName' => $member?->name,
            'membershipName' => $member?->membership?->name,
            'householdName' => $household?->member_names,
            'householdSize' => $household?->members->count() ?? 0,
            'isVolunteer' => (bool) ($member->is_volunteer ?? false),
            'roles' => auth()->user()?->getRoleNames()->all() ?? [],
        ];
    }
}
