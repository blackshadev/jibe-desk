<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use Filament\Widgets\Widget;

final class DashboardWidgets
{
    /**
     * @return list<class-string<Widget>>
     */
    public static function all(): array
    {
        return [
            MemberOverview::class,
            HouseholdMembersWidget::class,
            OpenInvoicesWidget::class,
            OpenPurchaseOrdersWidget::class,
            UnresolvedBankTransactionsWidget::class,
        ];
    }
}
