<?php

declare(strict_types=1);

namespace App\Filament\Admin\Navigation;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case MemberAdministration = 'member_administration';
    case Invoicing = 'invoicing';
    case Rental = 'rental';
    case Activities = 'activities';

    public function getLabel(): string
    {
        return __('labels.navigation_groups.' . $this->value);
    }
}
