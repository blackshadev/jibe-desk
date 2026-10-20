<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Pages;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\DashboardWidgets;
use App\Filament\Admin\Widgets\Dashboard\HouseholdMembersWidget;
use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use App\Models\User;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class DashboardWidgetVisibilityTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_registry_contains_the_existing_dashboard_widgets(): void
    {
        static::assertContains(MemberOverview::class, DashboardWidgets::all());
        static::assertContains(HouseholdMembersWidget::class, DashboardWidgets::all());
    }

    public function test_member_sees_member_overview_and_household_members(): void
    {
        $this->actingAs(User::factory()->memberUser()->createQuietly());

        static::assertTrue(MemberOverview::canView());
        static::assertTrue(HouseholdMembersWidget::canView());
    }

    public function test_staff_sees_member_overview_but_not_household_members(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        static::assertTrue(MemberOverview::canView());
        static::assertFalse(HouseholdMembersWidget::canView());
    }

    public function test_guest_cannot_view_any_dashboard_widget(): void
    {
        static::assertFalse(HouseholdMembersWidget::canView());
    }
}
